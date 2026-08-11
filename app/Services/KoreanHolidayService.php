<?php

namespace App\Services;

use App\Models\HolidayRecord;
use Illuminate\Support\Carbon;

class KoreanHolidayService
{
    /** @var array<int, array<string, string>> 연도별 공휴일 캐시 (요청당 반복 조회 방지) */
    private array $yearCache = [];

    public function isHoliday(Carbon $date): bool
    {
        return isset($this->forYear($date->year)[$date->toDateString()]);
    }

    public function isDayBeforeHoliday(Carbon $date): bool
    {
        return $this->isHoliday($date->copy()->addDay());
    }

    public function isDayAfterHoliday(Carbon $date): bool
    {
        return $this->isHoliday($date->copy()->subDay());
    }

    /**
     * 예측 알고리즘에서 유사도 비교용으로 쓰는 날짜의 공휴일 문맥을 반환한다.
     */
    public function context(Carbon $date): string
    {
        return match (true) {
            $this->isHoliday($date) => 'holiday',
            $this->isDayBeforeHoliday($date) => 'day_before_holiday',
            $this->isDayAfterHoliday($date) => 'day_after_holiday',
            default => 'normal',
        };
    }

    /**
     * @return array<string, string> 날짜(Y-m-d) => 공휴일명
     */
    public function forYear(int $year): array
    {
        return $this->yearCache[$year] ??= HolidayRecord::query()
            ->whereYear('date', $year)
            ->get()
            ->mapWithKeys(fn (HolidayRecord $record) => [$record->date->toDateString() => $record->name])
            ->all();
    }
}
