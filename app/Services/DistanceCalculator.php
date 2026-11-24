<?php

namespace App\Services;

use Carbon\Carbon;

class DistanceCalculator
{
    private const EARTH_RADIUS_KM = 6371.0088;

    /** Do points ke beech seedhi (great-circle) doori, km me */
    public function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param iterable $points  latitude, longitude, accuracy, recorded_at wale objects/arrays
     */
    public function calculate(iterable $points): array
    {
        $cfg = config('gps');

        // Time ke hisaab se sort
        $sorted = collect($points)
            ->map(fn ($p) => [
                'latitude'    => (float) data_get($p, 'latitude'),
                'longitude'   => (float) data_get($p, 'longitude'),
                'accuracy'    => (float) data_get($p, 'accuracy'),
                'recorded_at' => Carbon::parse(data_get($p, 'recorded_at')),
            ])
            ->sortBy(fn ($p) => $p['recorded_at']->getTimestamp())
            ->values();

        $anchor   = null;   // last "bharosemand" point jisse compare karte hain
        $last     = null;   // aakhri accepted point (last location ke liye)
        $totalM   = 0.0;
        $accepted = [];
        $rejected = [];
        $gaps     = [];
        $segment_km = 0;

        foreach ($sorted as $p) {

            // 1) Kharab accuracy
            if ($p['accuracy'] > $cfg['max_accuracy_m']) {
                $rejected[] = $p + ['reason' => 'low_accuracy'];
                continue;
            }

            // Pehla valid point
            if ($anchor === null) {
                $anchor = $last = $p;
                $accepted[] = $p + ['gap_before' => false,'segment_km' => 0];
                continue;
            }

            $seconds = $p['recorded_at']->getTimestamp() - $anchor['recorded_at']->getTimestamp();

            // 2) Time aage nahi badha
            if ($seconds <= 0) {
                $rejected[] = $p + ['reason' => 'duplicate_time'];
                continue;
            }

            $meters = $this->haversine(
                $anchor['latitude'], $anchor['longitude'],
                $p['latitude'], $p['longitude']
            ) * 1000;

            // 3) Lamba gap: distance mat jodo, naya starting point bana do
            if ($seconds > $cfg['max_gap_seconds']) {
                $gaps[] = [
                    'from'        => $anchor['recorded_at']->toIso8601String(),
                    'to'          => $p['recorded_at']->toIso8601String(),
                    'seconds'     => $seconds,
                    'straight_km' => round($meters / 1000, 3),
                ];
                $anchor = $last = $p;
                $accepted[] = $p + ['gap_before' => true,'segment_km' => 0];
                continue;
            }

            // 4) Unrealistic speed (jump / teleport)
            $speedKmh = ($meters / $seconds) * 3.6;
            if ($speedKmh > $cfg['max_speed_kmh']) {
                $rejected[] = $p + ['reason' => 'unrealistic_speed'];
                continue;
            }

            // 5) Drift: bahut kam hilna = ruke hue hain
            $threshold = max($cfg['min_movement_m'], $anchor['accuracy'], $p['accuracy']);
            if ($meters < $threshold) {
                $last = $p;   // last location update, par anchor wahi rehta hai
                continue;
            }

            // Sab check pass: distance jodo
            $totalM += $meters;
            $anchor = $last = $p;
            $accepted[] = $p + ['gap_before' => false,'segment_km' => round($meters / 1000, 4)];
        }

        $first = $accepted[0] ?? null;

        return [
            'total_km'         => round($totalM / 1000, 2),
            'first'            => $first,
            'last'             => $last,
            'duration_seconds' => ($first && $last)
                ? $last['recorded_at']->getTimestamp() - $first['recorded_at']->getTimestamp()
                : 0,
            'accepted_count'   => count($accepted),
            'rejected_count'   => count($rejected),
            'points'           => $accepted,   // map route ke liye
            'rejected'         => $rejected,
            'gaps'             => $gaps,
        ];
    }
}