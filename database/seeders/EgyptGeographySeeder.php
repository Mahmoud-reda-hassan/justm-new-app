<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Region;
use Illuminate\Database\Seeder;
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

        $country = Country::query()->updateOrCreate(
            ['name_ar' => $countryData['name_ar']],
            ['name_en' => $countryData['name_en'], 'is_active' => true],
        );

        $regions = [];
        foreach ($data['regions'] as $row) {
            $region = Region::query()->updateOrCreate(
                ['name' => $row['name']],
                [],
            );
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

            $governorate = Governorate::query()->updateOrCreate(
                ['country_id' => $country->id, 'name_ar' => $row['name_ar']],
                [
                    'region_id' => $regionId,
                    'name_en' => $row['name_en'],
                    'is_active' => true,
                ],
            );
            $governorates[$row['source_id']] = $governorate->id;
        }

        foreach ($data['cities'] as $row) {
            $governorateId = $governorates[$row['governorate_source_id']] ?? null;
            if ($governorateId === null) {
                throw new RuntimeException('A city references a missing legacy governorate.');
            }

            City::query()->updateOrCreate(
                ['governorate_id' => $governorateId, 'name_ar' => $row['name_ar']],
                ['name_en' => $row['name_en'], 'is_active' => true],
            );
        }
    }
}
