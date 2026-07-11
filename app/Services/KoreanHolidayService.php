<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class KoreanHolidayService
{
    public function isHoliday(Carbon $date): bool
    {
        return in_array($date->toDateString(), config('holidays.dates', []), true);
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
     * 예측 알고리즘에서 유사도 비교용으로 쓰는 날짜의 공휴일 문맥.
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
}
