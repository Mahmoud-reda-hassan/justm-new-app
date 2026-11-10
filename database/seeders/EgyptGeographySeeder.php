<?php

namespace Database\\Seeders;

use App\\Models\\City;
use App\\Models\\Country;
use App\\Models\\Governorate;
use App\\Models\\Region;
use Illuminate\\Database\\Seeder;
use RuntimeException;

class EgyptGeographySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/egypt_geography.json');
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $countryData = $data['country'] ?? null;
        if (! is_array($countryData) || ($countryData['name_ar'] ?? null) !== 'مصر') {
            throw new RuntimeException('The Egypt geography data file is invalid.');
        }

        $countryMatches = Country::query()
            ->where('name_ar', $countryData['name_ar'])
            ->orWhere('name_en', $countryData['name_en'])
            ->get();

        if ($countryMatches->count() > 1) {
            throw new RuntimeException('Multiple country records match the Egypt reference data.');
        }

        $country = $countryMatches->first() ?? new Country();
        $country->fill([
            'name_ar' => $countryData['name_ar'],
            'name_en' => $countryData['name_en'],
            'is_active' => true,
        ])->save();

        $regions = [];
        foreach ($data['regions'] as $row) {
            $shortName = preg_replace('/^إقليم\\s*/u', '', $row['name']);
            $matches = Region::query()->whereIn('name', array_unique([$row['name'], $shortName]))->get();

            if ($matches->count() > 1) {
                throw new RuntimeException('Multiple region records match a legacy region.');
            }

            $region = $matches->first() ?? new Region();
            $region->name = $row['name'];
            $region->save();
            $regions[$row['source_id']] = $region->id;
        }

        $governorates = [];
        foreach ($data['governorates'] as $row) {
            $regionId = $row['region_source_id'] === null
                ? null
                : ($regions[$row['region_source_id']] ?? null);

            if ($row['region_source_id'] !== null && $regionId === null) {
                throw new RuntimeException('A governorate references a missing legacy region.');
            }

            $matches = Governorate::query()
                ->where('country_id', $country->id)
                ->where(fn ($query) => $query
                    ->where('name_ar', $row['name_ar'])
                    ->orWhere('name_en', $row['name_en']))
                ->get();

            if ($matches->count() > 1) {
                throw new RuntimeException('Multiple governorates match a legacy geographic record.');
            }

            $governorate = $matches->first() ?? new Governorate();
            $governorate->fill([
                'country_id' => $country->id,
                'region_id' => $regionId,
                'name_ar' => $row['name_ar'],
                'name_en' => $row['name_en'],
                'is_active' => true,
            ])->save();

            $governorates[$row['source_id']] = $governorate->id;
        }

        foreach ($data['cities'] as $row) {
            $governorateId = $governorates[$row['governorate_source_id']] ?? null;
            if ($governorateId === null) {
                throw new RuntimeException('A city references a missing legacy governorate.');
            }

            $matches = City::query()
                ->where('governorate_id', $governorateId)
                ->where(fn ($query) => $query
                    ->where('name_ar', $row['name_ar'])
                    ->orWhere('name_en', $row['name_en']))
                ->get();

            if ($matches->count() > 1) {
                throw new RuntimeException('Multiple cities match a legacy geographic record.');
            }

            $city = $matches->first() ?? new City();
            $city->fill([
                'governorate_id' => $governorateId,
                'name_ar' => $row['name_ar'],
                'name_en' => $row['name_en'],
                'is_active' => true,
            ])->save();
        }
    }
}
