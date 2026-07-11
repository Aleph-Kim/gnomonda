<?php

namespace App\Services\Weather;

enum WeatherCategory: string
{
    case Clear = 'clear';
    case Cloudy = 'cloudy';
    case Rain = 'rain';
    case Snow = 'snow';
    case Unknown = 'unknown';

    /**
     * Open-Meteo(WMO) 날씨 코드를 카테고리로 변환한다.
     */
    public static function fromWeatherCode(?int $code): self
    {
        if ($code === null) {
            return self::Unknown;
        }

        return match (true) {
            in_array($code, [0, 1], true) => self::Clear,
            in_array($code, [2, 3, 45, 48], true) => self::Cloudy,
            in_array($code, [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82, 95, 96, 99], true) => self::Rain,
            in_array($code, [71, 73, 75, 77, 85, 86], true) => self::Snow,
            default => self::Unknown,
        };
    }
}
