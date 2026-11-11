<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\City;
use App\\Models\\Country;
use App\\Models\\Governorate;
use Illuminate\\Database\\Eloquent\\Builder;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\View\\View;
use Illuminate\\Validation\\Rule;
use Illuminate\\Validation\\ValidationException;

class GeographicSettingsController extends Controller
{
    private const ENTITIES = [
        'countries' => ['model' => Country::class, 'permission' => 'geography.countries', 'title' => 'الدول', 'singular' => 'الدولة'],
        'governorates' => ['model' => Governorate::class, 'permission' => 'geography.governorates', 'title' => 'المحافظات والمقاطعات', 'singular' => 'المحافظة / المقاطعة'],
        'cities' => ['model' => City::class, 'permission' => 'geography.cities', 'title' => 'المدن', 'singular' => 'المدينة'],
    ];

    public function index(Request $request, string $entity): View
    {
        $config = $this->config($entity);
        $this->authorizePermission($request, $config['permission'].'.view');
        $model = $config['model'];
        $query = $model::query();
        if ($entity === 'governorates') $query->with('country');
        if ($entity === 'cities') $query->with('governorate.country');
        $query->when($request->filled('q'), function (Builder $q) use ($request): void {
            $term = '%'.$request->string('q').'%';
            $q->where(fn (Builder $search) => $search->where('name_ar', 'like', $term)->orWhere('name_en', 'like', $term));
        });
        if ($entity === 'governorates') $query->orderBy('country_id')->orderBy('name_ar');
        else $query->orderBy('name_ar');
        $rows = $query->paginate(25)->withQueryString();

        return view('admin.geography.index', [
            'entity' => $entity, 'config' => $config, 'rows' => $rows,
            'countries' => Country::query()->where('is_active', true)->orderBy('name_ar')->get(),
            'governorates' => Governorate::query()->where('is_active', true)->with('country')->orderBy('name_ar')->get(),
        ]);
    }

    public function store(Request $request, string $entity): RedirectResponse
    {
        $config = $this->config($entity);
        $this->authorizePermission($request, $config['permission'].'.create');
        $data = $this->validated($request, $entity);
        $config['model']::query()->create($data + ['is_active' => true]);
        return back()->with('status', 'تمت إضافة '.$config['singular'].' بنجاح.');
    }

    public function update(Request $request, string $entity, int $id): RedirectResponse
    {
        $config = $this->config($entity);
        $this->authorizePermission($request, $config['permission'].'.update');
        $record = $config['model']::query()->findOrFail($id);
        $record->update($this->validated($request, $entity, $record));
        return back()->with('status', 'تم تحديث البيانات بنجاح.');
    }

    public function setActive(Request $request, string $entity, int $id): RedirectResponse
    {
        $config = $this->config($entity);
        $this->authorizePermission($request, $config['permission'].'.activate');
        $record = $config['model']::query()->findOrFail($id);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        if ($data['is_active']) {
            if ($entity === 'governorates' && ! $record->country()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['record' => 'لا يمكن تفعيل المحافظة قبل تفعيل الدولة التابعة لها.']);
            }
            if ($entity === 'cities' && $record->governorate_id && ! $record->governorate()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['record' => 'لا يمكن تفعيل المدينة قبل تفعيل المحافظة التابعة لها.']);
            }
        } else {
            $hasActiveChildren = match ($entity) {
                'countries' => $record->governorates()->where('is_active', true)->exists(),
                'governorates' => $record->cities()->where('is_active', true)->exists(),
                default => false,
            };
            if ($hasActiveChildren) {
                throw ValidationException::withMessages(['record' => 'لا يمكن إيقاف هذا السجل لوجود سجلات تابعة نشطة. أوقف السجلات التابعة أولًا.']);
            }
        }
        $record->update(['is_active' => (bool) $data['is_active']]);
        return back()->with('status', 'تم تحديث حالة السجل.');
    }

    private function validated(Request $request, string $entity, ?object $record = null): array
    {
        $rules = [
            'name_ar' => ['required', 'string', 'max:'.($entity === 'cities' ? 120 : 100)],
            'name_en' => ['required', 'string', 'max:'.($entity === 'cities' ? 120 : 100)],
        ];
        if ($entity === 'countries') {
            $rules['name_ar'][] = Rule::unique('countries', 'name_ar')->ignore($record?->id);
            $rules['name_en'][] = Rule::unique('countries', 'name_en')->ignore($record?->id);
        } elseif ($entity === 'governorates') {
            $rules['country_id'] = ['required', 'integer', Rule::exists('countries', 'id')->where('is_active', true)];
            $rules['name_ar'][] = Rule::unique('governorates', 'name_ar')->where('country_id', $request->input('country_id'))->ignore($record?->id);
            $rules['name_en'][] = Rule::unique('governorates', 'name_en')->where('country_id', $request->input('country_id'))->ignore($record?->id);
        } else {
            $rules['governorate_id'] = ['nullable', 'integer', Rule::exists('governorates', 'id')->where('is_active', true)];
            $rules['name_ar'][] = Rule::unique('cities', 'name_ar')->where('governorate_id', $request->input('governorate_id'))->ignore($record?->id);
            $rules['name_en'][] = Rule::unique('cities', 'name_en')->where('governorate_id', $request->input('governorate_id'))->ignore($record?->id);
        }
        return $request->validate($rules);
    }

    private function config(string $entity): array
    {
        abort_unless(isset(self::ENTITIES[$entity]), 404);
        return self::ENTITIES[$entity];
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403);
    }
}
