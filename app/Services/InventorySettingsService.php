<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InventorySettingsService
{
    public function allowsNegativeStock(bool $lockForUpdate = false): bool
    {
        $query = DB::table('system_settings')->where('setting_key', 'inventory.allow_negative_stock');
        if ($lockForUpdate && DB::transactionLevel() > 0) $query->lockForUpdate();
        return (string) ($query->value('setting_value') ?? '0') === '1';
    }
}
