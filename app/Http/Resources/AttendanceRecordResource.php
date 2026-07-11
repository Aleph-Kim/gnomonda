<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // 고유 id
            'id' => $this->id,
            // 날짜
            'date' => $this->date->format('Y-m-d'),
            // 출근 시간
            'check_in_time' => $this->check_in_time ? substr($this->check_in_time, 0, 5) : null,
            // 퇴근 시간
            'check_out_time' => $this->check_out_time ? substr($this->check_out_time, 0, 5) : null,
            // 미팅 여부
            'meeting' => $this->meeting,
        ];
    }
}
