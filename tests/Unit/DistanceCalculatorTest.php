<?php

namespace Tests\Unit;

// use PHPUnit\Framework\TestCase;
use App\Services\DistanceCalculator;
use Tests\TestCase;

class DistanceCalculatorTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new DistanceCalculator();
    }

    private function pt(float $lat, float $lng, float $acc, string $time): array
    {
        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $acc,
            'recorded_at' => "2026-11-23 $time",
        ];
    }

    public function test_haversine_one_degree_latitude(): void
    {
        $km = $this->calc->haversine(21.0, 81.0, 22.0, 81.0);
        $this->assertEqualsWithDelta(111.19, $km, 0.05);
    }

    public function test_same_point_is_zero(): void
    {
        $this->assertSame(0.0, $this->calc->haversine(21.25, 81.63, 21.25, 81.63));
    }

    public function test_sums_distance_between_consecutive_points(): void
    {
        // Har step ~111 m, 30 sec me (~13 km/h)
        $r = $this->calc->calculate([
            $this->pt(21.000, 81.0, 10, '09:00:00'),
            $this->pt(21.001, 81.0, 10, '09:00:30'),
            $this->pt(21.002, 81.0, 10, '09:01:00'),
        ]);

        $this->assertEqualsWithDelta(0.22, $r['total_km'], 0.01);
        $this->assertSame(60, $r['duration_seconds']);
    }

    public function test_rejects_low_accuracy_point(): void
    {
        $r = $this->calc->calculate([
            $this->pt(21.000, 81.0, 10, '09:00:00'),
            $this->pt(21.001, 81.0, 250, '09:00:30'),   // kharab signal
            $this->pt(21.002, 81.0, 10, '09:01:00'),
        ]);

        $this->assertSame(1, $r['rejected_count']);
        $this->assertSame('low_accuracy', $r['rejected'][0]['reason']);
    }

    public function test_rejects_unrealistic_jump(): void
    {
        $r = $this->calc->calculate([
            $this->pt(21.000, 81.0, 10, '09:00:00'),
            $this->pt(21.500, 81.0, 10, '09:00:30'),    // ~55 km, 30 sec me
            $this->pt(21.002, 81.0, 10, '09:01:00'),
        ]);

        $this->assertSame('unrealistic_speed', $r['rejected'][0]['reason']);
        $this->assertEqualsWithDelta(0.22, $r['total_km'], 0.01);   // jump judta nahi
    }

    public function test_long_gap_is_not_counted(): void
    {
        $r = $this->calc->calculate([
            $this->pt(21.000, 81.0, 10, '09:00:00'),
            $this->pt(21.045, 81.0, 10, '09:40:00'),    // 40 min baad, ~5 km door
        ]);

        $this->assertSame(0.0, $r['total_km']);
        $this->assertCount(1, $r['gaps']);
    }

    public function test_stationary_drift_is_ignored(): void
    {
        // Jagah par ruke, bas ~10 m ka jitter
        $r = $this->calc->calculate([
            $this->pt(21.00000, 81.0, 15, '09:00:00'),
            $this->pt(21.00005, 81.0, 15, '09:00:30'),
            $this->pt(21.00000, 81.00005, 15, '09:01:00'),
            $this->pt(21.00004, 81.0, 15, '09:01:30'),
        ]);

        $this->assertSame(0.0, $r['total_km']);
    }

    public function test_unsorted_input_is_sorted_by_time(): void
    {
        $r = $this->calc->calculate([
            $this->pt(21.002, 81.0, 10, '09:01:00'),
            $this->pt(21.000, 81.0, 10, '09:00:00'),
            $this->pt(21.001, 81.0, 10, '09:00:30'),
        ]);

        $this->assertEqualsWithDelta(0.22, $r['total_km'], 0.01);
    }
}
