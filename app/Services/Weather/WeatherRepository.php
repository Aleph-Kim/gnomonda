<?php

namespace App\Services\Weather;

use App\Models\WeatherDailyRecord;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

class WeatherRepository
{
    public function __construct(private readonly WeatherProvider $provider) {}

    public function forDate(Carbon $date): ?WeatherSnapshot
    {
        return $this->range($date, $date)[$date->toDateString()] ?? null;
    }

    /**
     * 캐시(DB)에 없는 날짜만 provider에서 가져와 저장한 뒤, 기간 전체를 반환한다.
     *
     * @return array<string, WeatherSnapshot>
     */
    public function range(Carbon $start, Carbon $end): array
    {
        $cached = WeatherDailyRecord::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (WeatherDailyRecord $record) => $record->date->toDateString());

        $requestedDates = collect(CarbonPeriod::create($start, $end))
            ->map(fn (Carbon $date) => $date->toDateString());

        if ($requestedDates->every(fn (string $date) => $cached->has($date))) {
            return $cached->map(fn (WeatherDailyRecord $record) => $record->toSnapshot())->all();
        }

        $snapshots = $cached->map(fn (WeatherDailyRecord $record) => $record->toSnapshot())->all();

        foreach ($this->provider->dailyRange($start, $end) as $date => $snapshot) {
            $snapshots[$date] = $snapshot;

            WeatherDailyRecord::updateOrCreate(['date' => $date], [
                'weather_code' => $snapshot->weatherCode,
                'precipitation_mm' => $snapshot->precipitationMm,
                'max_temp_c' => $snapshot->maxTempC,
            ]);
        }

        return $snapshots;
    }
}
