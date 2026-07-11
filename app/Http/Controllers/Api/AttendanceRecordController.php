<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Illuminate\Http\Request;

class AttendanceRecordController extends Controller
{
    /**
     * 조회
     */
    public function index(Request $request)
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $records = AttendanceRecord::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date')
            ->get();

        return AttendanceRecordResource::collection($records);
    }

    /**
     * 저장
     */
    public function store(StoreAttendanceRecordRequest $request)
    {
        $record = AttendanceRecord::updateOrCreate(
            $request->only('date', 'type'),
            $request->only('time'),
        );

        return AttendanceRecordResource::make($record);
    }

    /**
     * 삭제
     */
    public function destroy(AttendanceRecord $attendanceRecord)
    {
        $attendanceRecord->delete();

        return response()->noContent();
    }
}
