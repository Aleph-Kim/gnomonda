<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KoreanHolidayService;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    /**
     * 달력 표시용으로 DB에 저장된 공휴일을 그대로 반환, 계산·추정·실시간 API 호출 없음
     * holidays:sync 스케줄러가 매일 자정 최신 데이터로 갱신
     */
    public function index(Request $request, KoreanHolidayService $holidayService)
    {
        $year = (int) $request->query('year', now()->year);

        return response()->json(['data' => $holidayService->forYear($year)]);
    }
}
