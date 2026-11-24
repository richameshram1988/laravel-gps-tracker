<?php

namespace App\Services;
use App\Models\GpsLocation;
use Carbon\Carbon;

class LocationIngestService
{
    /**
     * @return array{received:int, stored:int, duplicates:int}
     */
    public function ingest(int $userId, array $points): array
    {
        $now = now();

        $rows = collect($points)
            ->map(fn ($p) => [
                'user_id'     => $userId,
                'latitude'    => round((float) $p['latitude'], 7),
                'longitude'   => round((float) $p['longitude'], 7),
                'accuracy'    => (float) $p['accuracy'],
                'recorded_at' => Carbon::parse($p['recorded_at'])->utc()->format('Y-m-d H:i:s'),
                'is_valid'    => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ])
            // Isi request ke andar ke duplicate timestamps hata do
            ->unique('recorded_at')
            ->values()
            ->all();

        // insertOrIgnore: DB ke unique key (user_id, recorded_at) par duplicate skip ho jata hai
        $stored = GpsLocation::query()->insertOrIgnore($rows);

        return [
            'received'   => count($points),
            'stored'     => $stored,
            'duplicates' => count($points) - $stored,
        ];
    }
}
