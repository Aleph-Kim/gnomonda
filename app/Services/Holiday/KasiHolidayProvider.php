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
            return [];
        }

        $holidays = [];

        // 이 API는 월 단위로만 조회되므로 1~12월을 모두 호출해 합친다.
        for ($month = 1; $month <= 12; $month++) {
            $holidays += $this->fetchMonth($year, $month);
        }

        return $holidays;
    }

    /**
     * @return array<string, string>
     */
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

        // 결과가 1건이면 배열이 아니라 단일 오브젝트로 온다.
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
