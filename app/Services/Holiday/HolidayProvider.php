<?php

namespace App\Services\Holiday;

interface HolidayProvider
{
    /**
     * 주어진 연도의 공휴일을 가져온다. 다른 제공자로 교체하려면 이 인터페이스만
     * 구현하면 된다.
     *
     * @return array<string, string> 날짜(Y-m-d) => 공휴일명
     */
    public function yearHolidays(int $year): array;
}
