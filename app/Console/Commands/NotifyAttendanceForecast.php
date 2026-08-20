<?php

namespace App\Console\Commands;

use App\Services\AttendanceForecastService;
use App\Services\KoreanHolidayService;
use App\Services\Slack\SlackNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class NotifyAttendanceForecast extends Command
{
    protected $signature = 'attendance:notify-forecast';

    protected $description = '오늘의 예상 출/퇴근 시간을 슬랙으로 발송';

    public function handle(
        AttendanceForecastService $forecastService,
        KoreanHolidayService $holidayService,
        SlackNotifier $slackNotifier,
    ): int {
        $today = Carbon::today();

        // 주말/공휴일은 출근하지 않는 날이므로 알림 스킵
        if ($today->isWeekend() || $holidayService->isHoliday($today)) {
            return self::SUCCESS;
        }

        $forecast = $forecastService->predict($today);

        // 과거 데이터 부족 등으로 예측이 불완전하면 메시지를 보내지 않음
        if ($forecast['check_in_time'] === null || $forecast['check_out_time'] === null) {
            return self::SUCCESS;
        }

        $slackNotifier->send(
            "예상 출근: {$forecast['check_in_time']} / 예상 퇴근: {$forecast['check_out_time']}"
        );

        return self::SUCCESS;
    }
}
