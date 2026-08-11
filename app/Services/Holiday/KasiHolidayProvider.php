<?php

namespace App\Services\Holiday;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class KasiHolidayProvider implements HolidayProvider
{
    private const ENDPOINT = 'https://apis.data.go.kr/B090041/openapi/service/SpcdeInfoService/getRestDeInfo';

    public function __construct(private readonly ?string $serviceKey) {}

    public function yearHolidays(int $year): array
    {
        if (empty($this->serviceKey)) {
            // 키 미설정 환경(예: 로컬)에서도 앱이 죽지 않도록 빈 결과로 처리
            return [];
        }

        $holidays = [];

        // 이 API는 월 단위로만 조회되므로 1~12월을 모두 호출해 합산
        for ($month = 1; $month <= 12; $month++) {
            $holidays += $this->fetchMonth($year, $month);
        }

        return $holidays;
    }

    private function fetchMonth(int $year, int $month): array
    {
        try {
            $response = Http::get(self::ENDPOINT, [
                'serviceKey' => $this->serviceKey,
                'solYear' => $year,
                'solMonth' => str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                '_type' => 'json',
                'numOfRows' => 100,
            ]);
        } catch (Throwable) {
            Log::error('KASI HOLIDAY API ERROR', ['year' => $year, 'month' => $month]);
            return [];
        }

        if (! $response->successful()) {
            Log::error('KASI HOLIDAY API ERROR', ['year' => $year, 'month' => $month, 'status' => $response->status()]);
            return [];
        }

        $items = $response->json('response.body.items.item') ?? [];

        // 결과 1건일 때는 배열이 아닌 단일 오브젝트 형태
        if (isset($items['locdate'])) {
            $items = [$items];
        }

        $holidays = [];

        foreach ($items as $item) {
            try {
                $date = Carbon::createFromFormat('Ymd', (string) $item['locdate'])->toDateString();
            } catch (Throwable) {
                Log::error('KASI HOLIDAY ITEM PARSE ERROR', ['year' => $year, 'month' => $month, 'item' => $item]);
                continue;
            }

            $holidays[$date] = $item['dateName'] ?? '공휴일';
        }

        return $holidays;
    }
}
