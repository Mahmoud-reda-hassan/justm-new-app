<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\PartyPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PartyController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'parties.view');

        $parties = Party::query()
            ->with(['city', 'partyRoles', 'users', 'phones' => fn ($q) => $q->where('is_active', true), 'addresses.city'])
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('q'));
                $query->where(function (Builder $search) use ($term): void {
                    $search->where('name', 'like', '%'.$term.'%')
                        ->orWhere('code', 'like', '%'.$term.'%')
                        ->orWhere('phone', 'like', '%'.$term.'%')
                        ->orWhere('mobile', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%')
                        ->orWhereHas('phones', fn (Builder $phones) => $phones->where('number', 'like', '%'.$term.'%'));
                });
            })
            ->when(
                $request->filled('role') && array_key_exists($request->input('role'), Party::ROLES),
                fn (Builder $query) => $query->whereHas('partyRoles', fn (Builder $roles) => $roles->where('role', $request->input('role'))),
            )
            ->when($request->input('status') === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.parties.index', [
            'parties' => $parties,
            'cities' => City::query()->where('is_active', true)->orderBy('name_ar')->get(),
            'partyRoles' => Party::ROLES,
            'loginRoles' => Party::LOGIN_ROLES,
            'canCreateUsers' => ($request->user()?->hasPermission('users.create') ?? false) && ($request->user()?->hasPermission('structure.scope.manage_all') ?? false),
            'stats' => [
                'total' => Party::query()->count(),
                'active' => Party::query()->where('is_active', true)->count(),
                'marketers' => Party::query()->whereHas('partyRoles', fn (Builder $query) => $query->where('role', 'marketer'))->count(),
                'deliveryAgents' => Party::query()->whereHas('partyRoles', fn (Builder $query) => $query->where('role', 'delivery_agent'))->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'parties.create');
        $data = $this->validatedData($request);
        $roles = $data['roles'];
        $useContactCollections = $request->boolean('contacts_schema');
        $phones = $useContactCollections ? ($data['phones'] ?? []) : ($data['phones'] ?? $this->legacyPhones($data));
        $addresses = $useContactCollections ? ($data['addresses'] ?? []) : ($data['addresses'] ?? $this->legacyAddresses($data));
        unset($data['roles'], $data['phones'], $data['addresses'], $data['contacts_schema']);

        $this->assertSinglePrimary($phones, 'phones');
        $this->assertSinglePrimary($addresses, 'addresses');

        DB::transaction(function () use ($data, $roles, $phones, $addresses): void {
            $party = Party::query()->create($data + ['is_active' => true]);
            $party->partyRoles()->createMany(array_map(
                fn (string $role): array => ['role' => $role],
                $roles,
            ));
            $this->syncPhones($party, $phones);
            $this->syncAddresses($party, $addresses);
        });

        return back()->with('status', 'تمت إضافة الطرف بنجاح.');
    }

    public function update(Request $request, Party $party): RedirectResponse
    {
        $this->authorizePermission($request, 'parties.update');
        $data = $this->validatedData($request, $party);
        $roles = $data['roles'];
        $useContactCollections = $request->boolean('contacts_schema');
        $phones = $useContactCollections ? ($data['phones'] ?? []) : ($data['phones'] ?? null);
        $addresses = $useContactCollections ? ($data['addresses'] ?? []) : ($data['addresses'] ?? null);
        unset($data['roles'], $data['phones'], $data['addresses'], $data['contacts_schema']);
        abort_if(
            $party->users()->exists() && count(array_intersect($roles, Party::LOGIN_ROLES)) === 0,
            422,
            'لا يمكن إزالة كل الصفات التشغيلية من طرف مرتبط بحسابات دخول.'
        );

        if ($phones !== null) {
            $this->assertSinglePrimary($phones, 'phones');
        }
        if ($addresses !== null) {
            $this->assertSinglePrimary($addresses, 'addresses');
        }

        DB::transaction(function () use ($party, $data, $roles, $phones, $addresses): void {
            $party->update($data);
            $party->partyRoles()->delete();
            $party->partyRoles()->createMany(array_map(
                fn (string $role): array => ['role' => $role],
                $roles,
            ));
            if ($phones !== null) {
                $this->syncPhones($party, $phones);
            }
            if ($addresses !== null) {
                $this->syncAddresses($party, $addresses);
            }
        });

        return back()->with('status', 'تم تحديث بيانات الطرف وصفاته.');
    }

    public function quickStoreCustomer(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'parties.create');
        $data = $request->validate([
            'entity_type' => ['required', Rule::in(['individual', 'company'])],
            'name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:190'],
            'phones' => ['required', 'array', 'min:1', 'max:20'],
            'phones.*.number' => ['required', 'string', 'max:40'],
            'phones.*.type' => ['required', Rule::in(['mobile', 'landline'])],
            'phones.*.is_whatsapp' => ['nullable', 'boolean'],
            'phones.*.is_telegram' => ['nullable', 'boolean'],
            'phones.*.is_primary' => ['nullable', 'boolean'],
            'addresses' => ['required', 'array', 'min:1', 'max:30'],
            'addresses.*.city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('is_active', true)],
            'addresses.*.title' => ['required', 'string', 'max:80'],
            'addresses.*.address' => ['required', 'string', 'max:4000'],
            'addresses.*.is_primary' => ['nullable', 'boolean'],
        ]);
        $this->assertSinglePrimary($data['phones'], 'phones');
        $this->assertSinglePrimary($data['addresses'], 'addresses');
        if (! collect($data['phones'])->contains(fn (array $phone): bool => (bool) ($phone['is_primary'] ?? false))) {
            $data['phones'][0]['is_primary'] = true;
        }
        if (! collect($data['addresses'])->contains(fn (array $address): bool => (bool) ($address['is_primary'] ?? false))) {
            $data['addresses'][0]['is_primary'] = true;
        }

        $customer = DB::transaction(function () use ($data): Party {
            $primaryPhone = collect($data['phones'])->firstWhere('is_primary', true) ?? $data['phones'][0];
            $primaryAddress = collect($data['addresses'])->firstWhere('is_primary', true) ?? $data['addresses'][0];
            $customer = Party::query()->create([
                'entity_type' => $data['entity_type'],
                'name' => trim($data['name']),
                'mobile' => $primaryPhone['type'] === 'mobile' ? trim($primaryPhone['number']) : null,
                'phone' => $primaryPhone['type'] === 'landline' ? trim($primaryPhone['number']) : null,
                'email' => $data['email'] ?? null,
                'city_id' => $primaryAddress['city_id'],
                'address' => trim($primaryAddress['address']),
                'is_active' => true,
            ]);
            $customer->partyRoles()->create(['role' => 'customer']);
            $this->syncPhones($customer, $data['phones']);
            $this->syncAddresses($customer, $data['addresses']);

            return $customer;
        });

        return response()->json(['customer' => $this->quickCustomerPayload($customer)], 201);
    }

    public function quickUpdateCustomer(Request $request, Party $party): JsonResponse
    {
        $this->authorizePermission($request, 'parties.update');
        abort_unless(
            $party->is_active && $party->partyRoles()->where('role', 'customer')->exists(),
            404
        );
        $data = $request->validate([
            'entity_type' => ['required', Rule::in(['individual', 'company'])],
            'name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:190'],
            'phones' => ['required', 'array', 'min:1', 'max:20'],
            'phones.*.id' => ['nullable', 'integer'],
            'phones.*.number' => ['required', 'string', 'max:40'],
            'phones.*.type' => ['required', Rule::in(['mobile', 'landline'])],
            'phones.*.is_whatsapp' => ['nullable', 'boolean'],
            'phones.*.is_telegram' => ['nullable', 'boolean'],
            'phones.*.is_primary' => ['nullable', 'boolean'],
            'addresses' => ['required', 'array', 'min:1', 'max:30'],
            'addresses.*.id' => ['nullable', 'integer'],
            'addresses.*.city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('is_active', true)],
            'addresses.*.title' => ['required', 'string', 'max:80'],
            'addresses.*.address' => ['required', 'string', 'max:4000'],
            'addresses.*.is_primary' => ['nullable', 'boolean'],
        ]);
        $this->assertSinglePrimary($data['phones'], 'phones');
        $this->assertSinglePrimary($data['addresses'], 'addresses');
        if (! collect($data['phones'])->contains(fn (array $phone): bool => (bool) ($phone['is_primary'] ?? false))) {
            $data['phones'][0]['is_primary'] = true;
        }
        if (! collect($data['addresses'])->contains(fn (array $address): bool => (bool) ($address['is_primary'] ?? false))) {
            $data['addresses'][0]['is_primary'] = true;
        }

        $primaryPhone = collect($data['phones'])->firstWhere('is_primary', true) ?? $data['phones'][0];
        $primaryAddress = collect($data['addresses'])->firstWhere('is_primary', true) ?? $data['addresses'][0];

        DB::transaction(function () use ($party, $data, $primaryPhone, $primaryAddress): void {
            $party->update([
                'entity_type' => $data['entity_type'],
                'name' => trim($data['name']),
                'email' => $data['email'] ?? null,
                'mobile' => $primaryPhone['type'] === 'mobile' ? trim($primaryPhone['number']) : null,
                'phone' => $primaryPhone['type'] === 'landline' ? trim($primaryPhone['number']) : null,
                'city_id' => $primaryAddress['city_id'],
                'address' => trim($primaryAddress['address']),
            ]);
            $this->syncPhones($party, $data['phones']);
            $this->syncAddresses($party, $data['addresses']);
        });

        return response()->json(['customer' => $this->quickCustomerPayload($party->fresh())]);
    }

    private function validateQuickCustomer(Request $request): array
    {
        return $request->validate([
            'entity_type' => ['required', Rule::in(['individual', 'company'])],
            'name' => ['required', 'string', 'max:180'],
            'mobile' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('is_active', true)],
            'address' => ['required', 'string', 'max:4000'],
        ]);
    }

    private function quickCustomerPayload(Party $customer): array
    {
        $customer->load([
            'phones' => fn ($query) => $query->where('is_active', true)->orderByDesc('is_primary')->orderBy('id'),
            'addresses' => fn ($query) => $query->where('is_active', true)->with([
                'city.shippingZone.warehouse.branch.company',
                'city.shippingZone.warehouse.type',
            ])->orderByDesc('is_primary')->orderBy('id'),
        ]);

        return [
            'id' => $customer->id,
            'entity_type' => $customer->entity_type,
            'name' => $customer->name,
            'mobile' => $customer->mobile,
            'phone' => $customer->phone,
            'phones' => $customer->phones->map(fn (PartyPhone $phone): array => [
                'id' => $phone->id,
                'number' => $phone->number,
                'type' => $phone->type,
                'isPrimary' => $phone->is_primary,
                'isWhatsapp' => $phone->is_whatsapp,
                'isTelegram' => $phone->is_telegram,
            ])->values(),
            'email' => $customer->email,
            'addresses' => $customer->addresses->map(fn (PartyAddress $address): array => [
                'id' => $address->id,
                'title' => $address->title,
                'address' => $address->address,
                'city' => $address->city?->name_ar,
                'city_id' => $address->city_id,
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
        ];
    }

    public function setActive(Request $request, Party $party): RedirectResponse
    {
        $this->authorizePermission($request, 'parties.activate');
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $party->update(['is_active' => (bool) $data['is_active']]);

        return back()->with('status', 'تم تحديث حالة الطرف.');
    }

    private function validatedData(Request $request, ?Party $party = null): array
    {
        return $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('parties', 'code')->ignore($party?->id)],
            'entity_type' => ['required', Rule::in(['individual', 'company'])],
            'name' => ['required', 'string', 'max:180'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('is_active', true)],
            'address' => ['nullable', 'string', 'max:4000'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'commercial_register' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', Rule::in(array_keys(Party::ROLES))],
            'contacts_schema' => ['nullable', 'boolean'],
            'phones' => ['required_if:contacts_schema,1', 'array', 'min:1', 'max:20'],
            'phones.*.id' => ['nullable', 'integer'],
            'phones.*.number' => ['required', 'string', 'max:40'],
            'phones.*.type' => ['required', Rule::in(['mobile', 'landline'])],
            'phones.*.is_whatsapp' => ['nullable', 'boolean'],
            'phones.*.is_telegram' => ['nullable', 'boolean'],
            'phones.*.is_primary' => ['nullable', 'boolean'],
            'addresses' => ['required_if:contacts_schema,1', 'array', 'min:1', 'max:30'],
            'addresses.*.id' => ['nullable', 'integer'],
            'addresses.*.city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('is_active', true)],
            'addresses.*.title' => ['required', 'string', 'max:80'],
            'addresses.*.address' => ['required', 'string', 'max:4000'],
            'addresses.*.is_primary' => ['nullable', 'boolean'],
        ]);
    }

    private function legacyPhones(array $data): array
    {
        $phones = [];
        if (! empty($data['mobile'])) {
            $phones[] = ['number' => $data['mobile'], 'type' => 'mobile', 'is_primary' => true];
        }
        if (! empty($data['phone'])) {
            $phones[] = ['number' => $data['phone'], 'type' => 'landline', 'is_primary' => $phones === []];
        }

        return $phones;
    }

    private function legacyAddresses(array $data): array
    {
        if (empty($data['address']) && empty($data['city_id'])) {
            return [];
        }

        return [[
            'city_id' => $data['city_id'] ?? null,
            'title' => 'العنوان الرئيسي',
            'address' => $data['address'] ?? '',
            'is_primary' => true,
        ]];
    }

    private function assertSinglePrimary(array $items, string $field): void
    {
        $primaryCount = collect($items)->filter(fn (array $item): bool => (bool) ($item['is_primary'] ?? false))->count();
        if ($primaryCount > 1) {
            throw ValidationException::withMessages([$field => 'حدد سجلًا رئيسيًا واحدًا فقط.']);
        }
    }

    private function syncPhones(Party $party, array $phones): void
    {
        $keptIds = [];
        foreach ($phones as $phone) {
            $id = $phone['id'] ?? null;
            if ($id) {
                $record = $party->phones()->whereKey($id)->firstOrFail();
                $record->update([
                    'number' => trim($phone['number']),
                    'type' => $phone['type'],
                    'is_whatsapp' => (bool) ($phone['is_whatsapp'] ?? false),
                    'is_telegram' => (bool) ($phone['is_telegram'] ?? false),
                    'is_primary' => (bool) ($phone['is_primary'] ?? false),
                    'is_active' => true,
                ]);
            } else {
                $record = $party->phones()->create([
                    'number' => trim($phone['number']),
                    'type' => $phone['type'],
                    'is_whatsapp' => (bool) ($phone['is_whatsapp'] ?? false),
                    'is_telegram' => (bool) ($phone['is_telegram'] ?? false),
                    'is_primary' => (bool) ($phone['is_primary'] ?? false),
                    'is_active' => true,
                ]);
            }
            $keptIds[] = $record->id;
        }

        $party->phones()->whereNotIn('id', $keptIds ?: [0])->update(['is_active' => false, 'is_primary' => false]);
        $active = $party->phones()->where('is_active', true)->orderByDesc('is_primary')->orderBy('id')->get();
        $primary = $active->firstWhere('is_primary', true) ?? $active->first();
        if ($primary) {
            $party->phones()->where('id', '<>', $primary->id)->update(['is_primary' => false]);
            $primary->update(['is_primary' => true]);
        }

        $party->update([
            'mobile' => $active->firstWhere('type', 'mobile')?->number,
            'phone' => $active->firstWhere('type', 'landline')?->number,
        ]);
    }

    private function syncAddresses(Party $party, array $addresses): void
    {
        $keptIds = [];
        foreach ($addresses as $address) {
            $id = $address['id'] ?? null;
            $values = [
                'city_id' => $address['city_id'] ?? null,
                'title' => trim($address['title']),
                'address' => trim($address['address']),
                'is_primary' => (bool) ($address['is_primary'] ?? false),
                'is_active' => true,
            ];
            if ($id) {
                $record = $party->addresses()->whereKey($id)->firstOrFail();
                $record->update($values);
            } else {
                $record = $party->addresses()->create($values);
            }
            $keptIds[] = $record->id;
        }

        $party->addresses()->whereNotIn('id', $keptIds ?: [0])->update(['is_active' => false, 'is_primary' => false]);
        $active = $party->addresses()->where('is_active', true)->orderByDesc('is_primary')->orderBy('id')->get();
        $primary = $active->firstWhere('is_primary', true) ?? $active->first();
        if ($primary) {
            $party->addresses()->where('id', '<>', $primary->id)->update(['is_primary' => false]);
            $primary->update(['is_primary' => true]);
        }

        $party->update([
            'city_id' => $primary?->city_id,
            'address' => $primary?->address,
        ]);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403);
        abort_unless(
            $request->user()?->hasPermission('structure.scope.manage_all'),
            403,
            'إدارة الأطراف تتطلب نطاقًا شاملًا حتى يكتمل ربط الأطراف بنطاق الشركة والفرع.'
        );
    }
}
