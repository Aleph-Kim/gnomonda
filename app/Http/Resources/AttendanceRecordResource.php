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
            // 출퇴근 타입
            'type' => $this->type->value,
            // 시간
            'time' => substr($this->time, 0, 5),
        ];
    }
}
