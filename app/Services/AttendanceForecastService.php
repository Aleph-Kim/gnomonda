<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Services\Weather\WeatherCategory;
use App\Services\Weather\WeatherRepository;
use App\Services\Weather\WeatherSnapshot;
use Illuminate\Support\Carbon;

class AttendanceForecastService
{
    // 예측에 사용할 과거 기록 조회 범위 (일)
    private const LOOKBACK_DAYS = 60;

    public function __construct(
        private readonly WeatherRepository $weatherRepository,
        private readonly KoreanHolidayService $holidayService,
    ) {}

    /**
     * 주어진 날짜의 예상 출근/퇴근 시간을 예측한다. (해당 날짜 이전 기록만 사용)
     */
    public function predict(Carbon $date): array
    {
        $since = $date->copy()->subDays(self::LOOKBACK_DAYS);
        $weatherRange = $this->weatherRepository->range($since, $date);

        return [
            'check_in_time' => $this->predictTime($date, 'check_in_time', $weatherRange),
            'check_out_time' => $this->predictTime($date, 'check_out_time', $weatherRange),
        ];
    }

    /**
     * 기간(양 끝 포함) 내 날짜별 예측을 한 번에 계산한다 (달력에서 미리보기로 사용).
     * 날씨는 필요한 전체 구간을 한 번에 가져와 공유한다 (날짜별로 외부 API를 반복 호출하지 않도록).
     *
     * @return array<string, array{check_in_time: ?string, check_out_time: ?string}>
     */
    public function predictRange(Carbon $start, Carbon $end): array
    {
        $weatherRange = $this->weatherRepository->range($start->copy()->subDays(self::LOOKBACK_DAYS), $end);

        $forecasts = [];
        $date = $start->copy();

        while ($date->lte($end)) {
            $forecasts[$date->toDateString()] = [
                'check_in_time' => $this->predictTime($date, 'check_in_time', $weatherRange),
                'check_out_time' => $this->predictTime($date, 'check_out_time', $weatherRange),
            ];
            $date->addDay();
        }

        return $forecasts;
    }

    /**
     * @param  array<string, WeatherSnapshot>  $weatherRange
     */
    private function predictTime(Carbon $date, string $column, array $weatherRange): ?string
    {
        $since = $date->copy()->subDays(self::LOOKBACK_DAYS);

        $records = AttendanceRecord::query()
            ->whereNotNull($column)
            // 미팅이 있는 날은 정상 출퇴근 패턴을 대표하지 않으므로 표본에서 제외한다.
            ->where('meeting', false)
            ->whereBetween('date', [$since->toDateString(), $date->copy()->subDay()->toDateString()])
            ->get(['date', $column]);

        if ($records->isEmpty()) {
            return null;
        }

        // 같은 요일 기록이 한 번도 없으면(예: 평일 기록만 있는데 주말을 예측) 추측하지 않는다.
        $hasSameWeekdayRecord = $records->contains(fn (AttendanceRecord $record) => $record->date->dayOfWeek === $date->dayOfWeek);

        if (! $hasSameWeekdayRecord) {
            return null;
        }

        $targetWeather = ($weatherRange[$date->toDateString()] ?? null)?->category();
        $targetContext = $this->holidayService->context($date);

        $weightedMinutes = 0.0;
        $totalWeight = 0.0;

        foreach ($records as $record) {
            $weight = $this->weight($record->date, $date, $weatherRange, $targetWeather, $targetContext);
            $weightedMinutes += $this->toMinutes($record->{$column}) * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0.0) {
            return null;
        }

        $avgMinutes = (int) round($weightedMinutes / $totalWeight);

        return sprintf('%02d:%02d', intdiv($avgMinutes, 60), $avgMinutes % 60);
    }

    /**
     * 과거 하루치 기록의 가중치. 최근 기록일수록, 목표 날짜와 요일/날씨/공휴일
     * 전후 문맥이 비슷할수록 더 크게 반영한다.
     *
     * @param  array<string, WeatherSnapshot>  $weatherRange
     */
    private function weight(Carbon $recordDate, Carbon $targetDate, array $weatherRange, ?WeatherCategory $targetWeather, string $targetContext): float
    {
        $daysAgo = $recordDate->diffInDays($targetDate);
        $weight = 30 / (30 + $daysAgo); // 최근 기록일수록 완만하게 큰 가중치

        if ($recordDate->dayOfWeek === $targetDate->dayOfWeek) {
            $weight *= 3.0;
        }

        if ($targetWeather !== null) {
            $recordWeather = ($weatherRange[$recordDate->toDateString()] ?? null)?->category();

            if ($recordWeather !== null && $recordWeather === $targetWeather) {
                $weight *= 1.5;
            }
        }

        if ($this->holidayService->context($recordDate) === $targetContext) {
            $weight *= 1.3;
        }

        return $weight;
    }

    private function toMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hour * 60 + $minute;
    }
}
