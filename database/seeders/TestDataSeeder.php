<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TestDataSeeder extends Seeder
{
    /**
     * 예측 알고리즘을 실제와 비슷한 데이터로 테스트해볼 수 있도록 최근 60일치 평일 출퇴근 기록을 채운다.
     * local 환경에서만 호출된다 (DatabaseSeeder 참고).
     */
    public function run(): void
    {
        $date = Carbon::today()->subDays(60);
        $end = Carbon::today()->subDay();

        while ($date->lte($end)) {
            if ($date->isWeekend()) {
                $date->addDay();

                continue;
            }

            $factory = AttendanceRecord::factory();

            // 가끔 미팅만 있고 출퇴근 기록은 없는 날도 섞어준다.
            if (fake()->boolean(10)) {
                $factory = $factory->meetingOnly();
            }

            $factory->create(['date' => $date->toDateString()]);

            $date->addDay();
        }
    }
}
