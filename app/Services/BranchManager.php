<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BranchManager
{
    public function create(array $attributes): Branch
    {
        return DB::transaction(function () use ($attributes): Branch {
            $company = Company::query()
                ->whereKey($attributes['company_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $company->is_active) {
                throw ValidationException::withMessages([
                    'company_id' => 'لا يمكن إنشاء فرع تابع لشركة موقوفة.',
                ]);
            }

            if ((bool) ($attributes['is_main'] ?? false)) {
                Branch::query()
                    ->where('company_id', $company->id)
                    ->update(['is_main' => false]);
            }

            return Branch::create($attributes);
        });
    }

    public function update(Branch $branch, array $attributes): Branch
    {
        return DB::transaction(function () use ($branch, $attributes): Branch {
            $current = Branch::query()
                ->whereKey($branch->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $companyId = (int) ($attributes['company_id'] ?? $current->company_id);
            $companyIds = collect([$current->company_id, $companyId])
                ->unique()
                ->sort()
                ->values();

            $companies = Company::query()
                ->whereIn('id', $companyIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $targetCompany = $companies->get($companyId);
            if (! $targetCompany || ! $targetCompany->is_active) {
                throw ValidationException::withMessages([
                    'company_id' => 'الشركة المحددة غير موجودة أو موقوفة.',
                ]);
            }

            if ($companyId !== (int) $current->company_id && $current->warehouses()->exists()) {
                throw ValidationException::withMessages(['company_id' => 'لا يمكن نقل فرع مرتبط بمخازن إلى شركة أخرى.']);
            }

            $isMain = (bool) ($attributes['is_main'] ?? $current->is_main);
            if ($isMain) {
                Branch::query()
                    ->where('company_id', $companyId)
                    ->where('id', '!=', $current->id)
                    ->update(['is_main' => false]);
            }

            $current->fill($attributes);
            $current->save();

            return $current->refresh();
        });
    }
}
