<?php

namespace App\Services;

use App\Enums\AttendanceType;
use App\Models\AttendanceRecord;
use Illuminate\Database\UniqueConstraintViolationException;

class AttendanceRecordService
{
    public function isAlreadyRegistered(string $date, AttendanceType $type): bool
    {
        $record = AttendanceRecord::query()->where('date', $date)->first();

        return match ($type) {
            AttendanceType::CheckIn => $record?->check_in_time !== null,
            AttendanceType::CheckOut => $record?->check_out_time !== null,
            AttendanceType::Meeting => $record?->meeting === true,
        };
    }

    // 같은 날짜에 대한 동시 요청(예: 토글 버튼 연타)으로 행이 이미 생성된 경우 재시도
    public function upsert(string $date, array $values): AttendanceRecord
    {
        try {
            $record = AttendanceRecord::updateOrCreate(['date' => $date], $values);
        } catch (UniqueConstraintViolationException) {
            $record = AttendanceRecord::updateOrCreate(['date' => $date], $values);
        }

        $this->deleteIfEmpty($record);

        return $record;
    }

    public function deleteIfEmpty(AttendanceRecord $record): void
    {
        if ($record->check_in_time === null && $record->check_out_time === null && $record->meeting === false) {
            $record->delete();
        }
    }
}
