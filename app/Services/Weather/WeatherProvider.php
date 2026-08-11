<?php

namespace App\Services\Weather;

use Illuminate\Support\Carbon;

interface WeatherProvider
{
    /**
     * 주어진 기간(양 끝 포함)의 일별 날씨 조회, 다른 제공자로 교체 시 이 인터페이스 구현만으로 대응
     * 반환값은 날짜(Y-m-d) => 스냅샷 형식
     */
    public function dailyRange(Carbon $start, Carbon $end): array;
}
