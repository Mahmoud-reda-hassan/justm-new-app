<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesOrderController extends Controller
{
    public function index(): View
    {
        $orders = SalesOrder::query()
            ->with(['customer', 'creatorParty'])
            ->latest('id')
            ->paginate(20);

        return view('admin.sales.orders.index', compact('orders'));
    }

    public function create(Request $request): View
    {
        $customers = Party::query()
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

        return view('admin.sales.orders.create', compact('customers', 'variants'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:parties,id'],
            'shipping_address' => ['nullable', 'string', 'max:2000'],
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
        $order = DB::transaction(function () use ($request, $data, $customer, $preparedItems, $subtotal, $discountTotal, $shippingFee): SalesOrder {
            $order = SalesOrder::query()->create([
                'customer_id' => $customer->id,
                'created_by' => $request->user()->id,
                'created_by_party_id' => $request->user()->party_id,
                'status' => 'draft',
                'shipping_address' => $data['shipping_address'] ?? null,
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

        return redirect()->route('admin.sales.orders.index')->with('status', 'تم حفظ أمر البيع '.$order->order_number.' كمسودة. لم يتم حجز مخزون أو إنشاء قيد مالي.');
    }
}
