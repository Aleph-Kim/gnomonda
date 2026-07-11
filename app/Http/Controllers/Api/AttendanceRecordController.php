<?php

namespace App\Http\Controllers\Api;

use App\Enums\AttendanceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyAttendanceRecordRequest;
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
     * 저장 (해당 날짜 행의 한 필드만 갱신, 나머지 필드는 보존)
     */
    public function store(StoreAttendanceRecordRequest $request)
    {
        $type = AttendanceType::from($request->validated('type'));

        $values = match ($type) {
            AttendanceType::CheckIn => ['check_in_time' => $request->validated('time')],
            AttendanceType::CheckOut => ['check_out_time' => $request->validated('time')],
            AttendanceType::Meeting => ['meeting' => (bool) $request->validated('meeting')],
        };

        $record = AttendanceRecord::updateOrCreate(
            ['date' => $request->validated('date')],
            $values,
        );

        $this->deleteIfEmpty($record);

        return AttendanceRecordResource::make($record);
    }

    /**
     * 삭제 (해당 날짜 행의 한 필드만 초기화, 모든 필드가 비면 행 자체를 삭제)
     */
    public function destroy(DestroyAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord)
    {
        $type = AttendanceType::from($request->validated('type'));

        match ($type) {
            AttendanceType::CheckIn => $attendanceRecord->check_in_time = null,
            AttendanceType::CheckOut => $attendanceRecord->check_out_time = null,
            AttendanceType::Meeting => $attendanceRecord->meeting = false,
        };

        $attendanceRecord->save();
        $this->deleteIfEmpty($attendanceRecord);

        return response()->noContent();
    }

    private function deleteIfEmpty(AttendanceRecord $record): void
    {
        if ($record->check_in_time === null && $record->check_out_time === null && $record->meeting === false) {
            $record->delete();
        }
    }
}
