<?php

namespace App\Services\Weather;

use Illuminate\Support\Carbon;

interface WeatherProvider
{
    /**
     * 주어진 기간(양 끝 포함)의 일별 날씨를 가져온다. 기상청 API 등 다른
     * 제공자로 교체하려면 이 인터페이스만 구현하면 된다.
     *
     * @return array<string, WeatherSnapshot> 날짜(Y-m-d) => 스냅샷
     */
    public function dailyRange(Carbon $start, Carbon $end): array;
}
