<?php

use App\Models\AttendanceRecord;
use App\Services\AttendanceForecastService;
use App\Services\Weather\WeatherProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 예측 로직 자체를 검증하는 테스트이므로 외부 날씨 API 호출은 항상 빈 결과로 고정한다.
    $this->app->bind(WeatherProvider::class, fn () => new class implements WeatherProvider
    {
        public function dailyRange(Carbon $start, Carbon $end): array
        {
            return [];
        }
    });
});

it('returns null when there is no history', function () {
    $service = app(AttendanceForecastService::class);

    expect($service->predict(Carbon::parse('2026-07-13')))->toBe([
        'check_in_time' => null,
        'check_out_time' => null,
    ]);
});

it('predicts the exact time when all same-weekday history agrees', function () {
    // 2026-07-13(월)에 대해 예측 -> 과거 월요일 기록만 있으면 그 값 그대로 예측된다.
    AttendanceRecord::create(['date' => '2026-07-06', 'check_in_time' => '10:00', 'check_out_time' => '18:00']); // 월
    AttendanceRecord::create(['date' => '2026-06-29', 'check_in_time' => '10:00', 'check_out_time' => '18:00']); // 월

    $service = app(AttendanceForecastService::class);
    $forecast = $service->predict(Carbon::parse('2026-07-13'));

    expect($forecast['check_in_time'])->toBe('10:00')
        ->and($forecast['check_out_time'])->toBe('18:00');
});

it('weighs same-weekday history more heavily than other weekdays', function () {
    // 2026-07-13(월)에 대해 예측 -> 월요일 기록(10:00)이 수요일 노이즈(11:00)보다 더 크게 반영돼야 한다.
    AttendanceRecord::create(['date' => '2026-07-06', 'check_in_time' => '10:00', 'check_out_time' => '18:00']); // 월
    AttendanceRecord::create(['date' => '2026-06-29', 'check_in_time' => '10:00', 'check_out_time' => '18:00']); // 월
    AttendanceRecord::create(['date' => '2026-07-08', 'check_in_time' => '11:00', 'check_out_time' => '19:00']); // 수 (다른 요일, 노이즈)

    $service = app(AttendanceForecastService::class);
    $forecast = $service->predict(Carbon::parse('2026-07-13'));

    // 정확히 10:00은 아니지만 11:00보다는 10:00에 훨씬 가까워야 한다.
    expect($forecast['check_in_time'] >= '10:00' && $forecast['check_in_time'] <= '10:20')->toBeTrue()
        ->and($forecast['check_out_time'] >= '18:00' && $forecast['check_out_time'] <= '18:20')->toBeTrue();
});

it('excludes meeting days from the prediction sample', function () {
    AttendanceRecord::create(['date' => '2026-07-06', 'check_in_time' => '10:00', 'check_out_time' => '18:00']);
    // 미팅이 있던 날은 출근을 아예 안 했거나 일찍 퇴근하는 등 이상치이므로 제외돼야 한다.
    AttendanceRecord::create(['date' => '2026-06-29', 'check_in_time' => '14:00', 'check_out_time' => '15:00', 'meeting' => true]);

    $service = app(AttendanceForecastService::class);
    $forecast = $service->predict(Carbon::parse('2026-07-13'));

    expect($forecast['check_in_time'])->toBe('10:00')
        ->and($forecast['check_out_time'])->toBe('18:00');
});

it('only uses records strictly before the target date', function () {
    AttendanceRecord::create(['date' => '2026-07-06', 'check_in_time' => '10:00', 'check_out_time' => '18:00']);
    // 미래 기록(예측 대상일 이후)은 사용하면 안 된다.
    AttendanceRecord::create(['date' => '2026-07-20', 'check_in_time' => '13:00', 'check_out_time' => '21:00']);

    $service = app(AttendanceForecastService::class);
    $forecast = $service->predict(Carbon::parse('2026-07-13'));

    expect($forecast['check_in_time'])->toBe('10:00')
        ->and($forecast['check_out_time'])->toBe('18:00');
});

it('exposes the forecast via the api endpoint', function () {
    AttendanceRecord::create(['date' => '2026-07-06', 'check_in_time' => '10:00', 'check_out_time' => '18:00']);

    $response = $this->getJson('/api/attendance-records/forecast?date=2026-07-13');

    $response->assertOk()->assertJson([
        'check_in_time' => '10:00',
        'check_out_time' => '18:00',
    ]);
});
