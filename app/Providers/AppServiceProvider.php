<?php

namespace App\Providers;

use App\Services\Holiday\HolidayProvider;
use App\Services\Holiday\KasiHolidayProvider;
use App\Services\Weather\OpenMeteoWeatherProvider;
use App\Services\Weather\WeatherProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 날씨 제공자를 여기서만 바꾸면 됨 (예: 기상청 API로 전환 시 driver 분기 추가)
        $this->app->bind(WeatherProvider::class, fn () => match (config('weather.driver')) {
            default => new OpenMeteoWeatherProvider(
                latitude: config('weather.latitude'),
                longitude: config('weather.longitude'),
                timezone: config('weather.timezone'),
            ),
        });

        $this->app->bind(HolidayProvider::class, fn () => new KasiHolidayProvider(
            serviceKey: config('services.kasi.key'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
