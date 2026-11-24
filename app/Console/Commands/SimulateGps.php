<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LocationIngestService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SimulateGps extends Command
{
    protected $signature = 'gps:simulate
                            {--date= : Date (Y-m-d, IST). Default: aaj}
                            {--user= : Sirf ek user id}';

    protected $description = 'Ek din ka simulated GPS data generate karta hai';

    private const INTERVAL_SEC = 30;

    public function handle(LocationIngestService $ingest): int
    {
        $date = $this->option('date') ?: now('Asia/Kolkata')->toDateString();

        $users = User::where('is_admin', false)
            ->when($this->option('user'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        if ($users->isEmpty()) {
            $this->error('Koi user nahi mila. Pehle php artisan db:seed chalao.');
            return self::FAILURE;
        }

        foreach ($users as $user) {
            mt_srand($user->id * 1000 + crc32($date));   // same input = same data

            $points = $this->buildDay($user->id, $date);
            $result = $ingest->ingest($user->id, $points);

            $this->info("{$user->name}: {$result['stored']} points stored, {$result['duplicates']} duplicates ({$date})");
        }

        return self::SUCCESS;
    }

    private function buildDay(int $userId, string $date): array
    {
        // Har user ka starting point thoda alag (Bilaspur ke aas-paas)
        $baseLat = 21.2514 + ($userId * 0.004);
        $baseLng = 81.6296 + ($userId * 0.004);

        $home   = [$baseLat,          $baseLng];
        $office = [$baseLat + 0.040,  $baseLng + 0.030];   // ~5 km
        $client = [$baseLat + 0.070,  $baseLng - 0.020];   // ~8 km

        // [type, from, to, minutes]
        $timeline = [
            ['move', $home,   $office, 30],
            ['stay', $office, $office, 60],
            ['move', $office, $client, 25],
            ['stay', $client, $client, 30],
            ['move', $client, $home,   35],
        ];

        $time   = Carbon::parse("$date 09:00:00", 'Asia/Kolkata');
        $points = [];

        foreach ($timeline as [$type, $from, $to, $minutes]) {
            $steps = intdiv($minutes * 60, self::INTERVAL_SEC);

            for ($i = 0; $i < $steps; $i++) {
                $f   = $i / max($steps - 1, 1);
                $lat = $from[0] + ($to[0] - $from[0]) * $f;
                $lng = $from[1] + ($to[1] - $from[1]) * $f;

                // Noise: chalte waqt ~10 m, ruke hue ~20 m (GPS drift)
                $noiseM = $type === 'stay' ? 20 : 10;

                $points[] = [
                    'latitude'    => $lat + $this->noise($noiseM),
                    'longitude'   => $lng + $this->noise($noiseM),
                    'accuracy'    => $type === 'stay' ? mt_rand(15, 30) : mt_rand(5, 20),
                    'recorded_at' => $time->copy()->utc()->toIso8601ZuluString(),
                ];

                $time->addSeconds(self::INTERVAL_SEC);
            }
        }

        return $this->injectBadData($points);
    }

    private function injectBadData(array $points): array
    {
        // 1) Unrealistic jump: ek point ~55 km door (teleport)
        $points[40]['latitude'] += 0.5;

        // 2) Kharab accuracy: 250 m
        $points[70]['accuracy'] = 250;

        // 3) Long gap: ~40 min ke points hata do (tunnel / app band)
        array_splice($points, 120, 80);

        return array_values($points);
    }

    // Meters ko degrees me noise ke roop me badalta hai (1 m ≈ 0.000009°)
    private function noise(int $maxMeters): float
    {
        return mt_rand(-$maxMeters * 100, $maxMeters * 100) / 100 * 0.000009;
    }
}