<?php

namespace App\Http\Requests;

use App\Enums\AttendanceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreAttendanceRecordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // 날짜
            'date' => ['required', 'date_format:Y-m-d'],
            // 출퇴근 타입
            'type' => ['required', new Enum(AttendanceType::class)],
            // 시간
            'time' => ['required', 'date_format:H:i'],
        ];
    }
}
