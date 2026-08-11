<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Services\Weather\WeatherCategory;
use App\Services\Weather\WeatherRepository;
use App\Services\Weather\WeatherSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceForecastService
{
    // 예측에 사용할 과거 기록 조회 범위 (일)
    private const LOOKBACK_DAYS = 60;

    // 예측 근거 문장에 인용할 최근 기록 수 (예: "최근 3번의 월요일")
    private const RECENT_SAMPLE = 3;

    private const WEEKDAY_LABELS = ['일', '월', '화', '수', '목', '금', '토'];

    private const WEATHER_LABELS = [
        'clear' => '맑은',
        'cloudy' => '흐린',
        'rain' => '비 오는',
        'snow' => '눈 오는',
    ];

    private const HOLIDAY_LABELS = [
        'holiday' => '공휴일',
        'day_before_holiday' => '공휴일 전날',
        'day_after_holiday' => '공휴일 다음날',
    ];

    public function __construct(
        private readonly WeatherRepository $weatherRepository,
        private readonly KoreanHolidayService $holidayService,
    ) {}

    // 주어진 날짜의 예상 출근/퇴근 시간 예측 (해당 날짜 이전 기록만 사용)
    public function predict(Carbon $date): array
    {
        $since = $date->copy()->subDays(self::LOOKBACK_DAYS);
        $weatherRange = $this->weatherRepository->range($since, $date);

        $checkIn = $this->computePrediction($date, 'check_in_time', $weatherRange);
        $checkOut = $this->computePrediction($date, 'check_out_time', $weatherRange);

        return [
            'check_in_time' => $checkIn['value'],
            'check_out_time' => $checkOut['value'],
            'explanation' => [
                'check_in_time' => $checkIn['explanation'],
                'check_out_time' => $checkOut['explanation'],
            ],
        ];
    }

    /**
     * 기간(양 끝 포함) 내 날짜별 예측을 한 번에 계산 (달력에서 미리보기로 사용)
     * 날씨는 필요한 전체 구간을 한 번에 가져와 공유, 날짜별 외부 API 반복 호출 방지
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

    private function predictTime(Carbon $date, string $column, array $weatherRange): ?string
    {
        return $this->computePrediction($date, $column, $weatherRange)['value'];
    }

    private function computePrediction(Carbon $date, string $column, array $weatherRange): array
    {
        $since = $date->copy()->subDays(self::LOOKBACK_DAYS);

        $records = AttendanceRecord::query()
            ->whereNotNull($column)
            // 미팅 있는 날은 정상 출퇴근 패턴 비대표로 판단해 표본에서 제외
            ->where('meeting', false)
            ->whereBetween('date', [$since->toDateString(), $date->copy()->subDay()->toDateString()])
            ->get(['date', $column]);

        if ($records->isEmpty()) {
            return ['value' => null, 'explanation' => null];
        }

        // 같은 요일 기록이 한 번도 없으면(예: 평일 기록만 있는데 주말을 예측) 예측 제외
        $hasSameWeekdayRecord = $records->contains(fn (AttendanceRecord $record) => $record->date->dayOfWeek === $date->dayOfWeek);

        if (! $hasSameWeekdayRecord) {
            return ['value' => null, 'explanation' => null];
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
            return ['value' => null, 'explanation' => null];
        }

        $avgMinutes = (int) round($weightedMinutes / $totalWeight);
        $value = sprintf('%02d:%02d', intdiv($avgMinutes, 60), $avgMinutes % 60);

        return [
            'value' => $value,
            'explanation' => $this->buildExplanation($date, $column, $value, $records, $weatherRange, $targetWeather, $targetContext),
        ];
    }

    // 예측값의 근거를 사람이 읽을 수 있는 한 문장으로 구성
    private function buildExplanation(Carbon $date, string $column, string $value, Collection $records, array $weatherRange, ?WeatherCategory $targetWeather, string $targetContext): string
    {
        $label = $column === 'check_in_time' ? '출근' : '퇴근';
        $clauses = [];

        $sameWeekday = $records
            ->filter(fn (AttendanceRecord $record) => $record->date->dayOfWeek === $date->dayOfWeek)
            ->sortByDesc(fn (AttendanceRecord $record) => $record->date)
            ->take(self::RECENT_SAMPLE);

        $weekdayAverage = $this->averageTime($sameWeekday, $column);
        if ($weekdayAverage !== null) {
            $weekdayLabel = self::WEEKDAY_LABELS[$date->dayOfWeek];
            $clauses[] = "최근 {$sameWeekday->count()}번의 {$weekdayLabel}요일 {$label} 시간 평균({$weekdayAverage})";
        }

        // Unknown 카테고리는 라벨이 없어 날씨 근거 문장에서 제외
        if ($targetWeather !== null && isset(self::WEATHER_LABELS[$targetWeather->value])) {
            $weatherMatches = $records
                ->filter(fn (AttendanceRecord $record) => ($weatherRange[$record->date->toDateString()] ?? null)?->category() === $targetWeather)
                ->sortByDesc(fn (AttendanceRecord $record) => $record->date)
                ->take(self::RECENT_SAMPLE);

            $weatherAverage = $this->averageTime($weatherMatches, $column);
            if ($weatherAverage !== null) {
                $weatherLabel = self::WEATHER_LABELS[$targetWeather->value];
                $clauses[] = "{$weatherLabel} 날씨 패턴({$weatherAverage})";
            }
        }

        if (isset(self::HOLIDAY_LABELS[$targetContext])) {
            $holidayMatches = $records->filter(fn (AttendanceRecord $record) => $this->holidayService->context($record->date) === $targetContext);

            // 공휴일 문맥은 시각값을 정당화하는 건 아니라 정황적 유사성 근거라 존재 여부만 확인
            if ($holidayMatches->isNotEmpty()) {
                $clauses[] = self::HOLIDAY_LABELS[$targetContext].' 기록';
            }
        }

        $basis = empty($clauses) ? '과거 기록' : implode(', ', $clauses);

        return "{$basis} 등을 종합적으로 고려하면 {$value} {$label} 정도로 예상됩니다.";
    }

    private function averageTime(Collection $records, string $column): ?string
    {
        if ($records->isEmpty()) {
            return null;
        }

        $avgMinutes = (int) round($records->avg(fn (AttendanceRecord $record) => $this->toMinutes($record->{$column})));

        return sprintf('%02d:%02d', intdiv($avgMinutes, 60), $avgMinutes % 60);
    }

    /**
     * 과거 하루치 기록의 가중치, 최근 기록일수록, 목표 날짜와 요일/날씨/공휴일 전후 문맥이 비슷할수록 더 크게 반영
     */
    private function weight(Carbon $recordDate, Carbon $targetDate, array $weatherRange, ?WeatherCategory $targetWeather, string $targetContext): float
    {
        $daysAgo = $recordDate->diffInDays($targetDate);
        // 최근 기록일수록 완만하게 큰 가중치
        $weight = 30 / (30 + $daysAgo);

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
