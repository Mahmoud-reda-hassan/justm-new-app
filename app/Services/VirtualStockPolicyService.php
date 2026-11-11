<?php

namespace App\\Services;

use Illuminate\\Support\\Facades\\DB;

class VirtualStockPolicyService
{
    public const STATUSES = [
        'draft' => 'قيد التأكيد',
        'deferred_delivery' => 'مؤجل التسليم',
        'unavailable' => 'غير متوفر',
        'customer_unresponsive' => 'العميل لا يستجيب',
        'cancelled' => 'إلغاء أمر البيع',
        'cancelled_unresponsive' => 'إلغاء من لا يستجيب',
        'preparing' => 'قيد التجهيز',
        'prepared' => 'تم التجهيز',
    ];

    public function defaults(): array
    {
        $all = array_keys(self::STATUSES);

        return [
            'create_order_statuses' => array_values(array_diff($all, ['cancelled', 'cancelled_unresponsive'])),
            'customer_service_matrix' => array_fill_keys(self::STATUSES, $all),
            'warehouse_statuses' => ['prepared'],
        ];
    }

    public function settings(): array
    {
        $defaults = $this->defaults();
        $stored = DB::table('system_settings')
            ->whereIn('setting_key', [
                'inventory.virtual_stock.create_order_statuses',
                'inventory.virtual_stock.customer_service_matrix',
                'inventory.virtual_stock.warehouse_statuses',
            ])->pluck('setting_value', 'setting_key');

        return [
            'create_order_statuses' => $this->decodeList(
                $stored['inventory.virtual_stock.create_order_statuses'] ?? null,
                $defaults['create_order_statuses']
            ),
            'customer_service_matrix' => $this->decodeMatrix(
                $stored['inventory.virtual_stock.customer_service_matrix'] ?? null,
                $defaults['customer_service_matrix']
            ),
            'warehouse_statuses' => $this->decodeList(
                $stored['inventory.virtual_stock.warehouse_statuses'] ?? null,
                $defaults['warehouse_statuses']
            ),
        ];
    }

    public function save(array $settings, int $userId): void
    {
        $values = [
            'inventory.virtual_stock.create_order_statuses' => $settings['create_order_statuses'] ?? [],
            'inventory.virtual_stock.customer_service_matrix' => $settings['customer_service_matrix'] ?? [],
            'inventory.virtual_stock.warehouse_statuses' => $settings['warehouse_statuses'] ?? [],
        ];

        foreach ($values as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                [
                    'setting_value' => json_encode($value, JSON_UNESCAPED_UNICODE),
                    'description' => 'سياسة احتساب المخزون الافتراضي حسب حالة أمر البيع والمنظور',
                    'updated_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function statusesFor(string $view, ?string $rowStatus = null): array
    {
        $settings = $this->settings();

        return match ($view) {
            'create_order' => $settings['create_order_statuses'],
            'customer_service' => $settings['customer_service_matrix'][$rowStatus] ?? [],
            'warehouse' => $settings['warehouse_statuses'],
            default => [],
        };
    }

    public function reservedQuantity(
        int $warehouseId,
        int $variantId,
        array $statuses,
        ?int $excludeOrderId = null
    ): float {
        if ($statuses === []) {
            return 0.0;
        }

        return (float) DB::table('sales_order_item_reservations as reservations')
            ->join('sales_order_items as items', 'items.id', '=', 'reservations.sales_order_item_id')
            ->join('sales_orders as orders', 'orders.id', '=', 'items.sales_order_id')
            ->where('reservations.warehouse_id', $warehouseId)
            ->where('reservations.product_variant_id', $variantId)
            ->where('reservations.status', 'active')
            ->whereIn('orders.status', $statuses)
            ->when($excludeOrderId, fn ($query) => $query->where('orders.id', '<>', $excludeOrderId))
            ->sum('reservations.reserved_quantity');
    }

    private function decodeList(?string $json, array $fallback): array
    {
        $decoded = $json === null ? null : json_decode($json, true);
        if (! is_array($decoded)) {
            return $fallback;
        }

        return array_values(array_intersect(array_unique($decoded), array_keys(self::STATUSES)));
    }

    private function decodeMatrix(?string $json, array $fallback): array
    {
        $decoded = $json === null ? null : json_decode($json, true);
        if (! is_array($decoded)) {
            return $fallback;
        }

        $matrix = [];
        foreach (self::STATUSES as $row => $label) {
            $matrix[$row] = $this->decodeList(
                isset($decoded[$row]) && is_array($decoded[$row])
                    ? json_encode($decoded[$row])
                    : null,
                $fallback[$row]
            );
        }

        return $matrix;
    }
}
