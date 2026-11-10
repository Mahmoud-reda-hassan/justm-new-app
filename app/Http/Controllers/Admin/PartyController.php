<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Party;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PartyController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'parties.view');

        $parties = Party::query()
            ->with(['city', 'partyRoles'])
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('q'));
                $query->where(function (Builder $search) use ($term): void {
                    $search->where('name', 'like', '%'.$term.'%')
                        ->orWhere('code', 'like', '%'.$term.'%')
                        ->orWhere('phone', 'like', '%'.$term.'%')
                        ->orWhere('mobile', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%');
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'parties.create');
        $data = $this->validatedData($request);
        $roles = $data['roles'];
        unset($data['roles']);

        DB::transaction(function () use ($data, $roles): void {
            $party = Party::query()->create($data + ['is_active' => true]);
            $party->partyRoles()->createMany(array_map(
                fn (string $role): array => ['role' => $role],
                $roles,
            ));
        });

        return back()->with('status', 'تمت إضافة الطرف بنجاح.');
    }

    public function update(Request $request, Party $party): RedirectResponse
    {
        $this->authorizePermission($request, 'parties.update');
        $data = $this->validatedData($request, $party);
        $roles = $data['roles'];
        unset($data['roles']);

        DB::transaction(function () use ($party, $data, $roles): void {
            $party->update($data);
            $party->partyRoles()->delete();
            $party->partyRoles()->createMany(array_map(
                fn (string $role): array => ['role' => $role],
                $roles,
            ));
        });

        return back()->with('status', 'تم تحديث بيانات الطرف وصفاته.');
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
        ]);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403);
    }
}
