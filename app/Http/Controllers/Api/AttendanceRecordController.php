<?php

namespace App\Http\Controllers\Api;

use App\Enums\AttendanceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyAttendanceRecordRequest;
use App\Http\Requests\StoreAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use App\Services\AttendanceForecastService;
use App\Services\AttendanceRecordService;
use App\Services\Slack\SlackNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceRecordController extends Controller
{
    public function __construct(
        private readonly AttendanceRecordService $attendanceRecordService,
        private readonly AttendanceForecastService $attendanceForecastService,
        private readonly SlackNotifier $slackNotifier,
    ) {}

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

    // 해당 날짜 행의 한 필드만 갱신, 나머지 필드는 보존
    public function store(StoreAttendanceRecordRequest $request)
    {
        $type = AttendanceType::from($request->validated('type'));

        $values = match ($type) {
            AttendanceType::CheckIn => ['check_in_time' => $request->validated('time')],
            AttendanceType::CheckOut => ['check_out_time' => $request->validated('time')],
            AttendanceType::Meeting => ['meeting' => (bool) $request->validated('meeting')],
        };

        $isFirstRegistration = ! $this->attendanceRecordService->isAlreadyRegistered($request->validated('date'), $type);
        $isToday = $request->validated('date') === now()->toDateString();

        $record = $this->attendanceRecordService->upsert($request->validated('date'), $values);

        // 출근/퇴근 당일 최초 등록 시에만 슬랙 알림 발송 (과거 날짜 등록·수정 및 미팅 여부 변경은 제외)
        $message = match ($type) {
            AttendanceType::CheckIn => 'He is coming...',
            AttendanceType::CheckOut => 'He is gone...',
            AttendanceType::Meeting => null,
        };

        if ($message !== null && $isFirstRegistration && $isToday) {
            $forecast = $this->attendanceForecastService->predict(Carbon::today());
            $predictedTime = $type === AttendanceType::CheckIn ? $forecast['check_in_time'] : $forecast['check_out_time'];
            $comparison = $this->attendanceForecastService->compareToActual($predictedTime, $request->validated('time'));

            $this->slackNotifier->send($comparison !== null ? "{$message}\n{$comparison}" : $message);
        }

        return AttendanceRecordResource::make($record);
    }

    // 해당 날짜 행의 한 필드만 초기화, 모든 필드가 비면 행 자체를 삭제
    public function destroy(DestroyAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord)
    {
        $type = AttendanceType::from($request->validated('type'));

        // 출/퇴근은 미래 날짜 삭제(초기화) 불가, 미팅은 예외
        abort_if($type !== AttendanceType::Meeting && $attendanceRecord->date->isFuture(), 422);

        match ($type) {
            AttendanceType::CheckIn => $attendanceRecord->check_in_time = null,
            AttendanceType::CheckOut => $attendanceRecord->check_out_time = null,
            AttendanceType::Meeting => $attendanceRecord->meeting = false,
        };

        $attendanceRecord->save();
        $this->attendanceRecordService->deleteIfEmpty($attendanceRecord);

        return response()->noContent();
    }

    public function forecast(Request $request)
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = $request->query('date')
            ? Carbon::createFromFormat('Y-m-d', $request->query('date'))
            : Carbon::today();

        return response()->json($this->attendanceForecastService->predict($date));
    }

    // 달력 미리보기용 한 달치 날짜별 예상 출/퇴근 시간
    public function forecastRange(Request $request)
    {
        $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $start = Carbon::create((int) $request->query('year'), (int) $request->query('month'), 1);

        return response()->json($this->attendanceForecastService->predictRange($start, $start->copy()->endOfMonth()));
    }
}
