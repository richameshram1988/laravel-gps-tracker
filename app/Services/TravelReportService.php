<?php

namespace App\Services;

use App\Models\GpsLocation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class TravelReportService
{
    public function __construct(private DistanceCalculator $calculator)
    {
    }

    /** Ek user ka full-day report */
    public function forUser(User $user, string $date, int $page = 1, int $perPage = 50): array
    {
        $result = $this->compute($user->id, $date);
        // dd($result);
        $points = collect($result['points']);

        return [
            'user' => $user->name, //['id' => $user->id, 'name' => $user->name],
            'date' => date("d-M-Y", strtotime($date)),
            'distance_km' => $result['total_km'],
            'first_location' => $this->formatPoint($result['first'])['time'],
            'last_location' => $this->formatPoint($result['last'])['time'],
            // 'duration'         => $this->formatDuration($result['duration_seconds']),
            // 'duration_seconds' => $result['duration_seconds'],
            // 'stats'            => [
            //     'accepted' => $result['accepted_count'],
            //     'rejected' => $result['rejected_count'],
            //     'gaps'     => count($result['gaps']),
            // ],
            // 'gaps'             => $result['gaps'],
            // 'route'            => $points->map(fn ($p) => [$p['latitude'], $p['longitude']])->all(),
            // 'history'          => [
            //     'data' => $points->forPage($page, $perPage)->values()
            //         ->map(fn ($p) => $this->formatPoint($p) + [
            //             'accuracy'   => $p['accuracy'],
            //             'segment_km' => $p['segment_km'],
            //             'gap_before' => $p['gap_before'],
            //         ])->all(),
            //     'page'      => $page,
            //     'per_page'  => $perPage,
            //     'total'     => $points->count(),
            //     'last_page' => (int) ceil($points->count() / $perPage),
            // ],
        ];
    }

    /** Sab users ka summary (filters ke saath), paginated */
    public function summary(string $date, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        [$start, $end] = $this->dayRange($date);

        $userIds = GpsLocation::query()
            ->whereBetween('recorded_at', [$start, $end])
            ->when($filters['user_id'] ?? null, fn($q, $id) => $q->where('user_id', $id))
            ->distinct()
            ->pluck('user_id');

        $rows = User::whereIn('id', $userIds)->orderBy('name')->get()
            ->map(function (User $u) use ($date) {
                $r = $this->compute($u->id, $date);

                return [
                    'user_id' => $u->id,
                    'user' => $u->name,
                    'date' => Carbon::parse($date)->format('d-M-Y'),
                    'distance_km' => $r['total_km'],
                    'first_location' => $r['first']
                        ? $this->localTime($r['first']['recorded_at']) : null,
                    'last_location' => $r['last']
                        ? $this->localTime($r['last']['recorded_at']) : null,
                ];
            })
            ->when(isset($filters['min_distance']), fn($c) => $c->where('distance_km', '>=', $filters['min_distance']))
            ->when(isset($filters['max_distance']), fn($c) => $c->where('distance_km', '<=', $filters['max_distance']))
            ->sortByDesc('distance_km')
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /** Export ke liye saari rows (bina pagination) */
    public function summaryRows(string $date, array $filters): array
    {
        return $this->summary($date, $filters, PHP_INT_MAX)->items();
    }

    public function forMapBased(User $user, string $date): array
    {
        $result = $this->compute($user->id, $date);
        // dd($result);
        $points = collect($result['points']);

        return $points->map(fn ($p) => ["latitude"=>$p['latitude'], "longitude"=>$p['longitude'],"recorded_at"=>$p['recorded_at']->format("Y-m-d H:i:s")])->all();
    }
    // ---------- helpers ----------

    private function compute(int $userId, string $date): array
    {
        [$start, $end] = $this->dayRange($date);

        $points = GpsLocation::query()
            ->where('user_id', $userId)
            ->whereBetween('recorded_at', [$start, $end])
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude', 'accuracy', 'recorded_at']);

        return $this->calculator->calculate($points);
    }

    /** IST ke din ka UTC range */
    private function dayRange(string $date): array
    {
        $tz = config('gps.timezone');

        return [
            Carbon::parse($date, $tz)->startOfDay()->utc(),
            Carbon::parse($date, $tz)->endOfDay()->utc(),
        ];
    }

    private function localTime(Carbon $t): string
    {
        return $t->copy()->setTimezone(config('gps.timezone'))->format('h:i A');
    }

    private function formatPoint(?array $p): ?array
    {
        if (!$p) {
            return null;
        }

        return [
            'time' => $this->localTime($p['recorded_at']),
            'latitude' => $p['latitude'],
            'longitude' => $p['longitude'],
        ];
    }

    private function formatDuration(int $seconds): string
    {
        return sprintf('%dh %02dm', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }
}