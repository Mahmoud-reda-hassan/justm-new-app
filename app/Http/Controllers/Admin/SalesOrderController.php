<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $customers = Party::query()
            ->with([
                'phones' => fn ($query) => $query->where('is_active', true)->orderByDesc('is_primary')->orderBy('id'),
                'addresses' => fn ($query) => $query->where('is_active', true)->with(['city.shippingZone.warehouse.branch.company', 'city.shippingZone.warehouse.type'])->orderByDesc('is_primary')->orderBy('id'),
            ])
            ->where('is_active', true)
            ->whereHas('partyRoles', fn (Builder $query) => $query->where('role', 'customer'))
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'mobile', 'address']);

        $variants = ProductVariant::query()
            ->with(['product', 'attributeValues.group'])
            ->where('is_active', true)
            ->whereHas('product', fn (Builder $query) => $query
                ->where('is_active', true)
                ->where('is_visible', true))
            ->whereHas('product.category', fn (Builder $query) => $query->where('is_active', true))
            ->whereHas('product.unit', fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('sku')
            ->get();

        $cities = City::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']);

        return view('admin.sales.orders.create', compact('customers', 'variants', 'cities'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:parties,id'],
            'shipping_address_id' => ['required', 'integer', 'exists:party_addresses,id'],
            'shipping_address' => ['required', 'string', 'max:2000'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
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

        $shippingDate = now()->addDays((int) $zone->delivery_days)->toDateString();
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

            $quantity = (float) $item['quantity'];
            $discount = round(($listPrice - $salePrice) * $quantity, 2);
            $lineTotal = round($salePrice * $quantity, 2);
            $subtotal += $lineTotal;
            $discountTotal += $discount;

            $preparedItems[] = [
                'variant' => $variant,
                'quantity' => $quantity,
                'list_price' => $listPrice,
                'sale_price' => $salePrice,
                'discount_amount' => $discount,
                'line_total' => $lineTotal,
            ];
        }

        $shippingFee = round((float) ($data['shipping_fee'] ?? 0), 2);
        $order = DB::transaction(function () use ($request, $data, $customer, $shippingAddress, $zone, $warehouse, $shippingDate, $preparedItems, $subtotal, $discountTotal, $shippingFee): SalesOrder {
            $order = SalesOrder::query()->create([
                'customer_id' => $customer->id,
                'shipping_address_id' => $shippingAddress->id,
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
                $order->items()->create([
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_sku' => $variant->sku,
                    'quantity' => $item['quantity'],
                    'list_price' => $item['list_price'],
                    'sale_price' => $item['sale_price'],
                    'discount_amount' => $item['discount_amount'],
                    'line_total' => $item['line_total'],
                ]);
            }

            return $order;
        });

        return redirect()->route('admin.sales.orders.create')->with('status', 'تم حفظ أمر البيع '.$order->order_number.' كمسودة. موعد الشحن '.$shippingDate.'، والمخزن '.$warehouse->name.'. لم يتم حجز مخزون أو إنشاء قيد مالي.');
    }
}
