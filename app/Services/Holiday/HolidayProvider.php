<?php

namespace App\Services\Holiday;

interface HolidayProvider
{
    /**
     * 연도별 공휴일 조회, 다른 제공자로 교체 시 이 인터페이스 구현만으로 대응
     * 반환값은 날짜(Y-m-d) => 공휴일명 형식
     */
    public function yearHolidays(int $year): array;
}
