<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\PartyPhone;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Services\SalesOrderInventoryService;
use App\Services\VirtualStockPolicyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesOrderController extends Controller
{
    public function index(): View
    {
        $query = SalesOrder::query()->with(['customer', 'creatorParty', 'creator']);
        $user = request()->user();

        // Operating partner accounts see only orders attributed to their own party.
        if ($user->party_id && $user->party?->partyRoles()
            ->whereIn('role', ['marketer', 'sales_intermediary'])->exists()) {
            $query->where('created_by_party_id', $user->party_id);
        }

        $query->when(request()->filled('q'), fn (Builder $builder) => $builder->where(function (Builder $search): void {
            $term = '%'.request()->string('q').'%';
            $search->where('order_number', 'like', $term)
                ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', $term))
                ->orWhereHas('creatorParty', fn (Builder $party) => $party->where('name', 'like', $term))
                ->orWhereHas('creator', fn (Builder $creator) => $creator->where('name', 'like', $term));
        }));

        $orders = $query->latest('id')->paginate(20)->withQueryString();

        return view('admin.sales.orders.index', compact('orders'));
    }

    public function customerDirectory(Request $request): JsonResponse
    {
        $phone = trim((string) $request->query('phone', ''));

        $deliveredCount = SalesOrder::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('customer_id', 'parties.id')
            ->where('status', 'delivered');

        $preparingCount = SalesOrder::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('customer_id', 'parties.id')
            ->where('status', 'preparing');

        $shippingCount = SalesOrder::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('customer_id', 'parties.id')
            ->where('status', 'shipping');

        // Drafts are newly created orders awaiting confirmation in the current workflow.
        $pendingConfirmationCount = SalesOrder::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('customer_id', 'parties.id')
            ->whereIn('status', ['draft', 'pending_confirmation']);

        $mostDeliveredCount = SalesOrder::query()
            ->where('status', 'delivered')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('customer_id')
            ->orderByDesc('aggregate')
            ->value('aggregate') ?? 0;

        $query = Party::query()
            ->select(['id', 'name', 'mobile', 'phone'])
            ->addSelect([
                'delivered_orders_count' => $deliveredCount,
                'preparing_orders_count' => $preparingCount,
                'shipping_orders_count' => $shippingCount,
                'pending_confirmation_orders_count' => $pendingConfirmationCount,
            ])
            ->with([
                'phones' => fn ($builder) => $builder
                    ->where('is_active', true)
                    ->orderByDesc('is_primary')
                    ->orderBy('id'),
                'addresses' => fn ($builder) => $builder
                    ->where('is_active', true)
                    ->with(['city.shippingZone.warehouse.branch.company', 'city.shippingZone.warehouse.type'])
                    ->orderByDesc('is_primary')
                    ->orderBy('id'),
            ])
            ->where('is_active', true)
            ->whereHas('partyRoles', fn (Builder $builder) => $builder->where('role', 'customer'));

        if ($phone !== '') {
            // Search only phone fields; matches are independent of pagination.
            $term = '%'.$phone.'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereHas('phones', fn (Builder $phones) => $phones
                    ->where('is_active', true)
                    ->where('number', 'like', $term))
                    ->orWhere('mobile', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        $customers = $query
            ->orderByDesc('delivered_orders_count')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $customers->getCollection()->transform(fn (Party $customer): array => [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phones->first()?->number ?: ($customer->mobile ?: $customer->phone),
            'phones' => $customer->phones->map(fn (PartyPhone $phone): array => [
                'id' => $phone->id,
                'number' => $phone->number,
                'type' => $phone->type,
                'isPrimary' => (bool) $phone->is_primary,
                'isWhatsapp' => (bool) $phone->is_whatsapp,
                'isTelegram' => (bool) $phone->is_telegram,
            ])->values(),
            'addresses' => $customer->addresses->map(fn (PartyAddress $address): array => [
                'id' => $address->id,
                'title' => $address->title,
                'address' => $address->address,
                'city' => $address->city?->name_ar,
                'cityId' => $address->city_id,
                'warehouseId' => $address->city?->shippingZone?->warehouse_id,
                'zone' => $address->city?->shippingZone?->is_active
                    && $address->city?->shippingZone?->warehouse?->is_active
                    && $address->city?->shippingZone?->warehouse?->type?->key === 'sales_dispatch'
                    && $address->city?->shippingZone?->warehouse?->branch?->is_active
                    && $address->city?->shippingZone?->warehouse?->branch?->company?->is_active
                        ? $address->city?->shippingZone?->name_ar : '',
                'feeMin' => $address->city?->shippingZone?->shipping_fee_min,
                'feeMax' => $address->city?->shippingZone?->shipping_fee_max,
                'deliveryDays' => $address->city?->shippingZone?->delivery_days,
                'isPrimary' => (bool) $address->is_primary,
            ])->values(),
            'delivered_orders_count' => (int) $customer->delivered_orders_count,
            'preparing_orders_count' => (int) $customer->preparing_orders_count,
            'shipping_orders_count' => (int) $customer->shipping_orders_count,
            'pending_confirmation_orders_count' => (int) $customer->pending_confirmation_orders_count,
            'is_most_delivered' => $mostDeliveredCount > 0
                && (int) $customer->delivered_orders_count === (int) $mostDeliveredCount,
        ]);

        return response()->json(['customers' => $customers]);
    }

    public function customerSelection(Party $customer): JsonResponse
    {
        abort_unless(
            $customer->is_active && $customer->partyRoles()->where('role', 'customer')->exists(),
            404
        );

        $customer->load([
            'phones' => fn ($query) => $query->where('is_active', true)->orderByDesc('is_primary')->orderBy('id'),
            'addresses' => fn ($query) => $query->where('is_active', true)->with([
                'city.shippingZone.warehouse.branch.company',
                'city.shippingZone.warehouse.type',
            ])->orderByDesc('is_primary')->orderBy('id'),
        ]);

        return response()->json(['customer' => [
            'id' => $customer->id,
            'entity_type' => $customer->entity_type,
            'name' => $customer->name,
            'mobile' => $customer->mobile,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'phones' => $customer->phones->map(fn (PartyPhone $phone): array => [
                'id' => $phone->id,
                'number' => $phone->number,
                'type' => $phone->type,
                'isPrimary' => $phone->is_primary,
                'isWhatsapp' => $phone->is_whatsapp,
                'isTelegram' => $phone->is_telegram,
            ])->values(),
            'addresses' => $customer->addresses->map(fn (PartyAddress $address): array => [
                'id' => $address->id,
                'title' => $address->title,
                'address' => $address->address,
                'city' => $address->city?->name_ar,
                'cityId' => $address->city_id,
                'warehouseId' => $address->city?->shippingZone?->warehouse_id,
                'zone' => $address->city?->shippingZone?->is_active
                    && $address->city?->shippingZone?->warehouse?->is_active
                    && $address->city?->shippingZone?->warehouse?->type?->key === 'sales_dispatch'
                    && $address->city?->shippingZone?->warehouse?->branch?->is_active
                    && $address->city?->shippingZone?->warehouse?->branch?->company?->is_active
                        ? $address->city?->shippingZone?->name_ar : '',
                'feeMin' => $address->city?->shippingZone?->shipping_fee_min,
                'feeMax' => $address->city?->shippingZone?->shipping_fee_max,
                'deliveryDays' => $address->city?->shippingZone?->delivery_days,
                'isPrimary' => $address->is_primary,
            ])->values(),
        ]]);
    }

    public function customerHistory(Request $request, Party $customer): JsonResponse
    {
        abort_unless(
            $customer->is_active && $customer->partyRoles()->where('role', 'customer')->exists(),
            404
        );

        $orders = SalesOrder::query()
            ->where('customer_id', $customer->id)
            ->when(
                $request->user()->party_id && $request->user()->party?->partyRoles()
                    ->whereIn('role', ['marketer', 'sales_intermediary'])->exists(),
                fn (Builder $query) => $query->where('created_by_party_id', $request->user()->party_id),
            )
            ->latest('id')
            ->limit(5)
            ->get(['order_number', 'status', 'total', 'created_at'])
            ->map(fn (SalesOrder $order): array => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => $order->total,
                'date' => $order->created_at?->toDateString(),
            ]);

        return response()->json(['orders' => $orders]);
    }

    public function create(Request $request): View
    {
        $variants = ProductVariant::query()
            ->with(['product.category', 'attributeValues.group'])
            ->where('is_active', true)
            ->whereHas('product', fn (Builder $query) => $query
                ->where('is_active', true)
                ->where('is_visible', true))
            ->whereHas('product.category', fn (Builder $query) => $query->where('is_active', true))
            ->whereHas('product.unit', fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('sku')
            ->get();

        // Build one storefront card per product, while retaining every sellable variant
        // for the product-details selector used by the legacy Just-M order screen.
        $catalogProducts = $variants->groupBy('product_id')->map(function ($productVariants): array {
            $firstVariant = $productVariants->first();
            $product = $firstVariant->product;

            return [
                'id' => (string) $product->id,
                'name' => $product->name,
                'category' => $product->category?->name ?? 'بدون قسم',
                'description' => $product->description ?? '',
                'image' => $product->image_path ? Storage::url($product->image_path) : null,
                'allow_preorder' => (bool) $product->allow_preorder,
                'variants' => $productVariants->map(fn (ProductVariant $variant): array => [
                    'id' => (string) $variant->id,
                    'sku' => $variant->sku,
                    'variant_name' => $variant->variant_name,
                    'list_price' => (float) $variant->list_price,
                    'minimum_sale_price' => (float) $variant->minimum_sale_price,
                    'attributes' => $variant->attributeValues->map(fn ($value): array => [
                        'group_id' => (string) $value->group_id,
                        'group_name' => $value->group?->name ?? 'المواصفة',
                        'value_id' => (string) $value->id,
                        'value' => $value->value,
                    ])->values()->all(),
                ])->values()->all(),
            ];
        })->values();

        $cities = City::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']);

        return view('admin.sales.orders.create', compact('variants', 'cities', 'catalogProducts'));
    }

    public function inventoryAvailability(Request $request, VirtualStockPolicyService $virtualStock): JsonResponse
    {
        $data = $request->validate(['address_id' => ['required', 'integer', 'exists:party_addresses,id']]);
        $address = PartyAddress::query()
            ->with(['city.shippingZone.warehouse.branch.company', 'city.shippingZone.warehouse.type'])
            ->whereKey($data['address_id'])
            ->where('is_active', true)
            ->first();

        $warehouse = $address?->city?->shippingZone?->warehouse;
        abort_unless(
            $address && $address->city?->is_active
            && $address->city?->shippingZone?->is_active
            && $warehouse?->is_active && $warehouse->branch?->is_active
            && $warehouse->branch?->company?->is_active
            && $warehouse->type?->key === 'sales_dispatch',
            422,
            'عنوان التوصيل غير مرتبط بمخزن بيع مباشر نشط.'
        );

        $countedStatuses = $virtualStock->statusesFor('create_order');
        $stocks = DB::table('warehouse_product_stocks as stock')
            ->leftJoin('sales_order_item_reservations as reservations', function ($join): void {
                $join->on('reservations.warehouse_id', '=', 'stock.warehouse_id')
                    ->on('reservations.product_variant_id', '=', 'stock.product_variant_id')
                    ->where('reservations.status', '=', 'active');
            })
            ->leftJoin('sales_order_items as reserved_items', 'reserved_items.id', '=', 'reservations.sales_order_item_id')
            ->leftJoin('sales_orders as reserved_orders', function ($join) use ($countedStatuses): void {
                $join->on('reserved_orders.id', '=', 'reserved_items.sales_order_id')
                    ->whereIn('reserved_orders.status', $countedStatuses);
            })
            ->where('stock.warehouse_id', $warehouse->id)
            ->groupBy('stock.product_variant_id', 'stock.quantity_on_hand')
            ->get([
                'stock.product_variant_id',
                'stock.quantity_on_hand',
                DB::raw('COALESCE(SUM(CASE WHEN reserved_orders.id IS NULL THEN 0 ELSE reservations.reserved_quantity END), 0) as reserved_quantity'),
            ])
            ->keyBy('product_variant_id');

        $variants = ProductVariant::query()
            ->with('product:id,allow_preorder')
            ->where('is_active', true)
            ->get(['id', 'product_id'])
            ->mapWithKeys(function (ProductVariant $variant) use ($stocks): array {
                $stock = $stocks->get($variant->id);
                $onHand = (float) ($stock->quantity_on_hand ?? 0);
                $reserved = (float) ($stock->reserved_quantity ?? 0);

                return [$variant->id => [
                    'available_quantity' => max(0, $onHand - $reserved),
                    'allow_preorder' => (bool) $variant->product->allow_preorder,
                ]];
            });

        return response()->json(['warehouse_id' => $warehouse->id, 'variants' => $variants]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:parties,id'],
            'shipping_address_id' => ['required', 'integer', 'exists:party_addresses,id'],
            'shipping_phone_id' => ['required', 'integer', 'exists:party_phones,id'],
            'shipping_address' => ['required', 'string', 'max:2000'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'shipping_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'items.*.sale_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $customer = Party::query()
            ->whereKey($data['customer_id'])
            ->where('is_active', true)
            ->whereHas('partyRoles', fn (Builder $query) => $query->where('role', 'customer'))
            ->first();

        if (! $customer) {
            throw ValidationException::withMessages(['customer_id' => 'العميل غير نشط أو لا يحمل صفة عميل.']);
        }

        $shippingPhone = PartyPhone::query()
            ->whereKey($data['shipping_phone_id'])
            ->where('party_id', $customer->id)
            ->where('is_active', true)
            ->first();

        if (! $shippingPhone) {
            throw ValidationException::withMessages([
                'shipping_phone_id' => 'رقم الهاتف المختار غير نشط أو لا يخص العميل المحدد.',
            ]);
        }

        $shippingAddress = PartyAddress::query()
            ->with(['city.shippingZone.warehouse.branch.company', 'city.shippingZone.warehouse.type'])
            ->whereKey($data['shipping_address_id'])
            ->where('party_id', $customer->id)
            ->where('is_active', true)
            ->first();

        $zone = $shippingAddress?->city?->shippingZone;
        $warehouse = $zone?->warehouse;
        if (
            ! $shippingAddress
            || ! $shippingAddress->city?->is_active
            || ! $zone
            || ! $zone->is_active
            || ! $warehouse
            || ! $warehouse->is_active
            || ! $warehouse->branch?->is_active
            || ! $warehouse->branch?->company?->is_active
            || $warehouse->type?->key !== 'sales_dispatch'
        ) {
            throw ValidationException::withMessages([
                'shipping_address_id' => 'عنوان التوصيل غير مرتبط بمنطقة شحن نشطة ومخزن بيع مباشر متاح. راجع إعداد الشحن المحلي.',
            ]);
        }

        $shippingFee = round((float) ($data['shipping_fee'] ?? 0), 2);
        if ($shippingFee < (float) $zone->shipping_fee_min || $shippingFee > (float) $zone->shipping_fee_max) {
            throw ValidationException::withMessages([
                'shipping_fee' => 'رسوم الشحن يجب أن تكون بين '.number_format((float) $zone->shipping_fee_min, 2).' و'.number_format((float) $zone->shipping_fee_max, 2).' جنيه حسب منطقة العنوان.',
            ]);
        }

        $earliestShippingDate = now()->startOfDay()->addDays((int) $zone->delivery_days)->toDateString();
        $shippingDate = $data['shipping_date'] ?? $earliestShippingDate;
        if ($shippingDate < $earliestShippingDate) {
            throw ValidationException::withMessages([
                'shipping_date' => 'موعد الشحن لا يمكن أن يكون قبل الموعد المتوقع حسب مدة التوصيل المحددة للعنوان ('.$earliestShippingDate.').',
            ]);
        }
        $preparedItems = [];
        $subtotal = 0.0;
        $discountTotal = 0.0;

        foreach ($data['items'] as $index => $item) {
            $variant = ProductVariant::query()
                ->with(['product.category', 'product.unit'])
                ->whereKey($item['product_variant_id'])
                ->where('is_active', true)
                ->whereHas('product', fn (Builder $query) => $query->where('is_active', true)->where('is_visible', true))
                ->first();

            if (! $variant || ! $variant->product->category->is_active || ! $variant->product->unit->is_active) {
                throw ValidationException::withMessages(["items.$index.product_variant_id" => 'أحد أكواد المنتجات لم يعد متاحًا للبيع.']);
            }

            $salePrice = round((float) $item['sale_price'], 2);
            $listPrice = (float) $variant->list_price;
            $minimumPrice = (float) $variant->minimum_sale_price;

            if ($salePrice < $minimumPrice || $salePrice > $listPrice) {
                throw ValidationException::withMessages([
                    "items.$index.sale_price" => 'سعر البيع يجب أن يكون بين الحد الأدنى وسعر القائمة.',
                ]);
            }

            $quantity = (int) $item['quantity'];
            $discount = round(($listPrice - $salePrice) * $quantity, 2);
            $lineTotal = round($salePrice * $quantity, 2);
            $subtotal += $lineTotal;
            $discountTotal += $discount;

            $preparedItems[] = [
                'variant' => $variant,
                'allow_preorder' => (bool) $variant->product->allow_preorder,
                'quantity' => $quantity,
                'list_price' => $listPrice,
                'sale_price' => $salePrice,
                'discount_amount' => $discount,
                'line_total' => $lineTotal,
            ];
        }

        $shippingFee = round((float) ($data['shipping_fee'] ?? 0), 2);
        $order = DB::transaction(function () use ($request, $data, $customer, $shippingPhone, $shippingAddress, $zone, $warehouse, $shippingDate, $preparedItems, $subtotal, $discountTotal, $shippingFee): SalesOrder {
            $order = SalesOrder::query()->create([
                'customer_id' => $customer->id,
                'shipping_address_id' => $shippingAddress->id,
                'shipping_phone_id' => $shippingPhone->id,
                'shipping_phone' => $shippingPhone->number,
                'shipping_city_id' => $shippingAddress->city_id,
                'shipping_zone_id' => $zone->id,
                'shipping_warehouse_id' => $warehouse->id,
                'shipping_zone_name' => $zone->name_ar,
                'shipping_warehouse_name' => $warehouse->name,
                'shipping_fee_min' => $zone->shipping_fee_min,
                'shipping_fee_max' => $zone->shipping_fee_max,
                'shipping_days' => $zone->delivery_days,
                'shipping_type' => $zone->shipping_type,
                'shipping_date' => $shippingDate,
                'created_by' => $request->user()->id,
                'created_by_party_id' => $request->user()->party_id,
                'status' => 'draft',
                'shipping_address' => $data['shipping_address'],
                'shipping_fee' => $shippingFee,
                'subtotal' => round($subtotal, 2),
                'discount_total' => round($discountTotal, 2),
                'total' => round($subtotal + $shippingFee, 2),
                'notes' => $data['notes'] ?? null,
            ]);

            $order->update(['order_number' => 'SO-'.now()->format('Y').'-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($preparedItems as $item) {
                $variant = $item['variant'];
                $orderItem = $order->items()->create([
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_sku' => $variant->sku,
                    'quantity' => $item['quantity'],
                    'list_price' => $item['list_price'],
                    'sale_price' => $item['sale_price'],
                    'discount_amount' => $item['discount_amount'],
                    'line_total' => $item['line_total'],
                ]);
                app(SalesOrderInventoryService::class)->reserve(
                    $orderItem,
                    $warehouse,
                    (float) $item['quantity'],
                    (bool) $item['allow_preorder'],
                    (int) $request->user()->id,
                );
            }

            return $order;
        });

        return redirect()->route('admin.sales.orders.create')->with('status', 'تم حفظ أمر البيع '.$order->order_number.' كمسودة. موعد الشحن '.$shippingDate.'، والمخزن '.$warehouse->name.'. تم حجز المتاح بالمخزن، وتسجيل العجز ككمية طلب مسبق للأصناف التي تسمح بذلك؛ لم يتم إنشاء قيد مالي.');
    }
}
