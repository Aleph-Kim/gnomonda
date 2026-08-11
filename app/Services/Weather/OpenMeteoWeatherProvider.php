<?php

namespace App\Services\Weather;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenMeteoWeatherProvider implements WeatherProvider
{
    private const FORECAST_ENDPOINT = 'https://api.open-meteo.com/v1/forecast';

    private const ARCHIVE_ENDPOINT = 'https://archive-api.open-meteo.com/v1/archive';

    public function __construct(
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly string $timezone,
    ) {}

    public function dailyRange(Carbon $start, Carbon $end): array
    {
        $today = Carbon::today($this->timezone);
        $snapshots = [];

        // 과거 날짜는 archive API, 오늘 이후는 forecast API로 분리 조회
        if ($start->lt($today)) {
            $snapshots += $this->fetch(self::ARCHIVE_ENDPOINT, $start, $end->lt($today) ? $end : $today->copy()->subDay());
        }

        if ($end->gte($today)) {
            $snapshots += $this->fetch(self::FORECAST_ENDPOINT, $start->gte($today) ? $start : $today, $end);
        }

        return $snapshots;
    }

    private function fetch(string $endpoint, Carbon $start, Carbon $end): array
    {
        if ($start->gt($end)) {
            return [];
        }

        try {
            $response = Http::timeout(5)->get($endpoint, [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'timezone' => $this->timezone,
                'daily' => 'weathercode,precipitation_sum,temperature_2m_max',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]);
        } catch (Throwable) {
            // 네트워크 장애 등으로 날씨 조회 실패해도 예측 자체는 계속 동작
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $daily = $response->json('daily');

        if (empty($daily['time'])) {
            return [];
        }

        $snapshots = [];

        foreach ($daily['time'] as $index => $date) {
            $snapshots[$date] = new WeatherSnapshot(
                date: $date,
                weatherCode: $daily['weathercode'][$index] ?? null,
                precipitationMm: (float) ($daily['precipitation_sum'][$index] ?? 0),
                maxTempC: isset($daily['temperature_2m_max'][$index]) ? (float) $daily['temperature_2m_max'][$index] : null,
            );
        }

        return $snapshots;
    }
}
