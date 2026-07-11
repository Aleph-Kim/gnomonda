<?php

namespace App\Services\Weather;

final class WeatherSnapshot
{
    public function __construct(
        public readonly string $date,
        public readonly ?int $weatherCode,
        public readonly float $precipitationMm,
        public readonly ?float $maxTempC,
    ) {}

    public function category(): WeatherCategory
    {
        return WeatherCategory::fromWeatherCode($this->weatherCode);
    }
}
