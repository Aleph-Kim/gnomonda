<?php

namespace App\Http\Requests;

use App\Enums\AttendanceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreAttendanceRecordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // 날짜 (출/퇴근은 미래 날짜 등록 불가, 미팅은 예외)
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::when(
                    $this->input('type') !== AttendanceType::Meeting->value,
                    ['before_or_equal:today'],
                ),
            ],
            // 갱신할 필드 구분자
            'type' => ['required', new Enum(AttendanceType::class)],
            // 출/퇴근 시간 (type이 check_in 또는 check_out일 때만)
            'time' => [
                'required_if:type,check_in,check_out',
                'prohibited_if:type,meeting',
                'date_format:H:i',
            ],
            // 미팅 여부 (type이 meeting일 때만)
            'meeting' => [
                'required_if:type,meeting',
                'prohibited_unless:type,meeting',
                'boolean',
            ],
        ];
    }
}
