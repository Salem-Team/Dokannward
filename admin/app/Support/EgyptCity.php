<?php

namespace App\Support;

/**
 * Maps Nominatim / OSM labels onto the storefront city list
 * so checkout can auto-fill a courier-ready city.
 */
class EgyptCity
{
    /** @var array<string, string> */
    private const ALIASES = [
        'cairo' => 'Cairo',
        'al qahirah' => 'Cairo',
        'el qahira' => 'Cairo',
        'cairo governorate' => 'Cairo',
        'muhafazat al qahirah' => 'Cairo',
        'القاهرة' => 'Cairo',
        'محافظة القاهرة' => 'Cairo',
        'giza' => 'Giza',
        'al jizah' => 'Giza',
        'el giza' => 'Giza',
        'giza governorate' => 'Giza',
        'الجيزة' => 'Giza',
        'محافظة الجيزة' => 'Giza',
        'alexandria' => 'Alexandria',
        'al iskandariyah' => 'Alexandria',
        'alex' => 'Alexandria',
        'الإسكندرية' => 'Alexandria',
        'الاسكندرية' => 'Alexandria',
        'new cairo' => 'New Cairo',
        'new cairo city' => 'New Cairo',
        'el tagamoa' => 'New Cairo',
        'tagamoa' => 'New Cairo',
        'the 5th settlement' => 'New Cairo',
        'fifth settlement' => 'New Cairo',
        'التجمع' => 'New Cairo',
        'التجمع الخامس' => 'New Cairo',
        '6th of october' => '6th of October',
        '6 october' => '6th of October',
        'sixth of october' => '6th of October',
        '6th of october city' => '6th of October',
        'أكتوبر' => '6th of October',
        'مدينة 6 أكتوبر' => '6th of October',
        'sheikh zayed' => 'Sheikh Zayed',
        'sheikh zayed city' => 'Sheikh Zayed',
        'الشيخ زايد' => 'Sheikh Zayed',
        'maadi' => 'Maadi',
        'al maadi' => 'Maadi',
        'el maadi' => 'Maadi',
        'المعادي' => 'Maadi',
        'heliopolis' => 'Heliopolis',
        'misr el gedida' => 'Heliopolis',
        'masr el gedida' => 'Heliopolis',
        'مصر الجديدة' => 'Heliopolis',
        'nasr city' => 'Nasr City',
        'madinat nasr' => 'Nasr City',
        'مدينة نصر' => 'Nasr City',
        'zamalek' => 'Zamalek',
        'الزمالك' => 'Zamalek',
        'mohandessin' => 'Mohandessin',
        'mohandiseen' => 'Mohandessin',
        'المهندسين' => 'Mohandessin',
        'helwan' => 'Helwan',
        'حلوان' => 'Helwan',
        'shubra el kheima' => 'Shubra El Kheima',
        'shubra el-kheima' => 'Shubra El Kheima',
        'شبرا الخيمة' => 'Shubra El Kheima',
        'qalyub' => 'Qalyub',
        'قليوب' => 'Qalyub',
        'banha' => 'Banha',
        'بنها' => 'Banha',
        'mansoura' => 'Mansoura',
        'al mansurah' => 'Mansoura',
        'المنصورة' => 'Mansoura',
        'tanta' => 'Tanta',
        'طنطا' => 'Tanta',
        'zagazig' => 'Zagazig',
        'الزقازيق' => 'Zagazig',
        'damietta' => 'Damietta',
        'دمياط' => 'Damietta',
        'port said' => 'Port Said',
        'بورسعيد' => 'Port Said',
        'ismailia' => 'Ismailia',
        'الإسماعيلية' => 'Ismailia',
        'suez' => 'Suez',
        'السويس' => 'Suez',
        'kafr el sheikh' => 'Kafr El Sheikh',
        'كفر الشيخ' => 'Kafr El Sheikh',
        'damanhur' => 'Damanhur',
        'دمنهور' => 'Damanhur',
        'el mahalla el kubra' => 'El Mahalla El Kubra',
        'mahalla' => 'El Mahalla El Kubra',
        'المحلة الكبرى' => 'El Mahalla El Kubra',
        'shibin el kom' => 'Shibin El Kom',
        'شبين الكوم' => 'Shibin El Kom',
        'fayoum' => 'Fayoum',
        'faiyum' => 'Fayoum',
        'الفيوم' => 'Fayoum',
        'beni suef' => 'Beni Suef',
        'بني سويف' => 'Beni Suef',
        'minya' => 'Minya',
        'المنيا' => 'Minya',
        'assuit' => 'Assiut',
        'assiut' => 'Assiut',
        'asyut' => 'Assiut',
        'أسيوط' => 'Assiut',
        'sohag' => 'Sohag',
        'سوهاج' => 'Sohag',
        'qena' => 'Qena',
        'قنا' => 'Qena',
        'luxor' => 'Luxor',
        'الأقصر' => 'Luxor',
        'aswan' => 'Aswan',
        'أسوان' => 'Aswan',
        'hurghada' => 'Hurghada',
        'الغردقة' => 'Hurghada',
        'el gouna' => 'El Gouna',
        'الجونة' => 'El Gouna',
        'sharm el sheikh' => 'Sharm El Sheikh',
        'شرم الشيخ' => 'Sharm El Sheikh',
        'dahab' => 'Dahab',
        'دهب' => 'Dahab',
        'marsa alam' => 'Marsa Alam',
        'مرسى علم' => 'Marsa Alam',
        'marsa matrouh' => 'Marsa Matrouh',
        'matruh' => 'Marsa Matrouh',
        'مطروح' => 'Marsa Matrouh',
        'arish' => 'Arish',
        'العريش' => 'Arish',
    ];

    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $key = mb_strtolower($value);
        $key = str_replace(['-', '_'], ' ', $key);
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        $stripped = trim(preg_replace('/\b(governorate|city|محافظة|مدينة)\b/u', '', $key) ?? $key);
        $stripped = preg_replace('/\s+/', ' ', $stripped) ?? $stripped;

        return self::ALIASES[$stripped] ?? null;
    }

    /**
     * @param  array<string, mixed>  $address
     */
    public static function fromNominatim(array $address): ?string
    {
        foreach (['neighbourhood', 'suburb', 'city_district', 'quarter', 'city', 'town', 'village', 'municipality', 'county', 'state'] as $key) {
            $mapped = self::normalize(isset($address[$key]) ? (string) $address[$key] : null);
            if ($mapped) {
                return $mapped;
            }
        }

        return null;
    }

    /**
     * Nominatim often labels Fifth Settlement / Tagamoa pins as "Cairo".
     * Refine using district text and a coarse Egypt bounding box.
     */
    public static function refine(
        ?string $city,
        array $address,
        float $latitude,
        float $longitude,
        ?string $placeName = null,
    ): ?string {
        $fromDistrict = self::fromDistrictLabels($address, $placeName);
        if ($fromDistrict) {
            return $fromDistrict;
        }

        $fromCoords = self::fromCoordinates($latitude, $longitude);
        if ($fromCoords && ($city === null || $city === 'Cairo' || $city === $fromCoords)) {
            return $fromCoords;
        }

        return $city;
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private static function fromDistrictLabels(array $address, ?string $placeName): ?string
    {
        $parts = [$placeName];
        foreach ($address as $value) {
            if (is_scalar($value)) {
                $parts[] = (string) $value;
            }
        }

        $haystack = mb_strtolower(trim(implode(' ', array_filter($parts))));
        if ($haystack === '') {
            return null;
        }

        $rules = [
            'New Cairo' => [
                'new cairo', 'tagamoa', 'tagammo', 'tagammu', 'el tagamoa', 'fifth settlement',
                'first settlement', 'akademeya', 'academy', 'ganoob el akademeya', 'lotus',
                'banafseg', 'yasmine', 'madinaty', 'mivida', 'katameya', 'mostakbal', 'el rehab',
                'التجمع', 'الأكاديمية', 'الاكاديمية', 'لوتس', 'التجمع الخامس',
            ],
            'Sheikh Zayed' => ['sheikh zayed', 'el sheikh zayed', 'الشيخ زايد'],
            '6th of October' => ['6th of october', '6 october', 'october city', 'أكتوبر', 'مدينة 6 أكتوبر'],
            'Nasr City' => ['nasr city', 'madinat nasr', 'مدينة نصر'],
            'Heliopolis' => ['heliopolis', 'misr el gedida', 'masr el gedida', 'مصر الجديدة'],
            'Maadi' => ['maadi', 'el maadi', 'المعادي'],
            'Zamalek' => ['zamalek', 'الزمالك'],
        ];

        foreach ($rules as $city => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $city;
                }
            }
        }

        return null;
    }

    private static function fromCoordinates(float $latitude, float $longitude): ?string
    {
        if ($latitude >= 29.90 && $latitude <= 30.15 && $longitude >= 31.35 && $longitude <= 31.62) {
            return 'New Cairo';
        }

        if ($latitude >= 30.03 && $latitude <= 30.08 && $longitude >= 31.31 && $longitude <= 31.40) {
            return 'Nasr City';
        }

        if ($latitude >= 30.08 && $latitude <= 30.13 && $longitude >= 31.30 && $longitude <= 31.37) {
            return 'Heliopolis';
        }

        if ($latitude >= 30.05 && $latitude <= 30.069 && $longitude >= 31.215 && $longitude <= 31.235) {
            return 'Zamalek';
        }

        if ($latitude >= 29.94 && $latitude <= 30.01 && $longitude >= 31.25 && $longitude <= 31.33) {
            return 'Maadi';
        }

        if ($latitude >= 30.04 && $latitude <= 30.065 && $longitude >= 31.19 && $longitude <= 31.22) {
            return 'Mohandessin';
        }

        if ($latitude >= 30.00 && $latitude <= 30.09 && $longitude >= 30.94 && $longitude <= 31.06) {
            return 'Sheikh Zayed';
        }

        if ($latitude >= 29.88 && $latitude <= 30.04 && $longitude >= 30.84 && $longitude <= 31.02) {
            return '6th of October';
        }

        return null;
    }
}
