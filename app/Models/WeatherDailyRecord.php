<?php

namespace App\Models;

use App\Services\Weather\WeatherSnapshot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'weather_code', 'precipitation_mm', 'max_temp_c'])]
class WeatherDailyRecord extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'weather_code' => 'integer',
            'precipitation_mm' => 'float',
            'max_temp_c' => 'float',
        ];
    }

    public function toSnapshot(): WeatherSnapshot
    {
        return new WeatherSnapshot(
            date: $this->date->toDateString(),
            weatherCode: $this->weather_code,
            precipitationMm: $this->precipitation_mm,
            maxTempC: $this->max_temp_c,
        );
    }
}
