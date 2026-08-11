<?php

namespace App\Console\Commands;

use App\Models\HolidayRecord;
use App\Services\Holiday\HolidayProvider;
use Illuminate\Console\Command;

class SyncHolidays extends Command
{
    protected $signature = 'holidays:sync';

    protected $description = '공공데이터포털 특일 정보 API에서 공휴일을 가져와 DB에 저장한다';

    public function handle(HolidayProvider $provider): int
    {
        $year = now()->year;

        // 올해 + 내년까지 동기화해서, 연말에도 다음 해 달력에 공휴일이 미리 보이게 한다.
        $this->syncYear($provider, $year);
        $this->syncYear($provider, $year + 1);

        return self::SUCCESS;
    }

    private function syncYear(HolidayProvider $provider, int $year): void
    {
        $holidays = $provider->yearHolidays($year);

        if (empty($holidays)) {
            // API 실패 등으로 못 가져온 경우 기존 데이터를 지우지 않고 유지한다.
            return;
        }

        foreach ($holidays as $date => $name) {
            HolidayRecord::updateOrCreate(['date' => $date], ['name' => $name]);
        }
    }
}
