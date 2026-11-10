<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductCodeGenerator
{
    public function generate(int $count = 1): array
    {
        if ($count < 1 || $count > 20) {
            throw new RuntimeException('عدد الأكواد المطلوب يجب أن يكون من 1 إلى 20.');
        }

        return DB::transaction(function () use ($count): array {
            $counter = DB::table('product_code_counters')->where('id', 1)->lockForUpdate()->first();

            if (! $counter) {
                throw new RuntimeException('عداد أكواد المنتجات غير مهيأ.');
            }

            $sequence = (int) $counter->last_sequence;
            $codes = [];

            while (count($codes) < $count) {
                $sequence++;
                if ($sequence > 9999999999) {
                    throw new RuntimeException('انتهت مساحة أكواد المنتجات المتاحة.');
                }

                $body = $counter->prefix.str_pad((string) $sequence, 10, '0', STR_PAD_LEFT);
                $code = $body.$this->checkDigit($body);

                if (! ProductVariant::query()->where('sku', $code)->exists()) {
                    $codes[] = $code;
                }
            }

            DB::table('product_code_counters')->where('id', 1)->update([
                'last_sequence' => $sequence,
                'updated_at' => now(),
            ]);

            return $codes;
        });
    }

    private function checkDigit(string $twelveDigits): int
    {
        if (! preg_match('/^\d{12}$/', $twelveDigits)) {
            throw new RuntimeException('صيغة الكود الداخلي غير صحيحة.');
        }

        $sum = 0;
        for ($index = 0; $index < 12; $index++) {
            $digit = (int) $twelveDigits[$index];
            $sum += $index % 2 === 0 ? $digit : $digit * 3;
        }

        return (10 - ($sum % 10)) % 10;
    }
}
