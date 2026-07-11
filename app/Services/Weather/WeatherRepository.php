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
        $fetched = $this->provider->dailyRange($start, $end);

        foreach ($fetched as $date => $snapshot) {
            $snapshots[$date] = $snapshot;

            WeatherDailyRecord::updateOrCreate(['date' => $date], [
                'weather_code' => $snapshot->weatherCode,
                'precipitation_mm' => $snapshot->precipitationMm,
                'max_temp_c' => $snapshot->maxTempC,
            ]);
        }

        // provider가 값을 못 준 날짜(예보 가능 범위 밖 등)도 빈 값으로 캐시해서, 매 요청마다
        // 같은 실패를 반복 조회하지 않도록 한다.
        foreach ($requestedDates as $date) {
            if (! isset($snapshots[$date]) && ! $cached->has($date)) {
                WeatherDailyRecord::updateOrCreate(['date' => $date], [
                    'weather_code' => null,
                    'precipitation_mm' => 0,
                    'max_temp_c' => null,
                ]);
            }
        }

        return $snapshots;
    }
}
