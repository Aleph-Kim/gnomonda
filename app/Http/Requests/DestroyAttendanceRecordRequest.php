<?php

namespace App\Http\Requests;

use App\Enums\AttendanceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class DestroyAttendanceRecordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // 초기화할 필드 구분자
            'type' => ['required', new Enum(AttendanceType::class)],
        ];
    }
}
