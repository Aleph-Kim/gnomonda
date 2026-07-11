<?php

use App\Services\KoreanHolidayService;
use Illuminate\Support\Carbon;

it('recognizes a configured holiday', function () {
    $service = new KoreanHolidayService;

    expect($service->isHoliday(Carbon::parse('2026-01-01')))->toBeTrue()
        ->and($service->isHoliday(Carbon::parse('2026-01-02')))->toBeFalse();
});

it('recognizes the day before and after a holiday', function () {
    $service = new KoreanHolidayService;

    // 2026-05-05(어린이날) 기준
    expect($service->isDayBeforeHoliday(Carbon::parse('2026-05-04')))->toBeTrue()
        ->and($service->isDayAfterHoliday(Carbon::parse('2026-05-06')))->toBeTrue()
        ->and($service->context(Carbon::parse('2026-05-04')))->toBe('day_before_holiday')
        ->and($service->context(Carbon::parse('2026-05-06')))->toBe('day_after_holiday')
        ->and($service->context(Carbon::parse('2026-05-05')))->toBe('holiday')
        ->and($service->context(Carbon::parse('2026-05-10')))->toBe('normal');
});
