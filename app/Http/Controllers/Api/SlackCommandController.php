<?php

namespace App\Http\Controllers\Api;

use App\Enums\AttendanceType;
use App\Http\Controllers\Controller;
use App\Services\AttendanceRecordService;
use App\Services\Slack\SlackNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SlackCommandController extends Controller
{
    // 슬래시 커맨드로 출/퇴근 등록 (/출근, /퇴근), 현재 시각 기준
    public function handle(Request $request, AttendanceRecordService $attendanceRecordService, SlackNotifier $slackNotifier)
    {
        $allowedChannelId = config('services.slack.allowed_channel_id');

        if (! empty($allowedChannelId) && $request->input('channel_id') !== $allowedChannelId) {
            return response()->json([
                'response_type' => 'ephemeral',
                'text' => '이 채널에서는 사용할 수 없는 명령어입니다.',
            ]);
        }

        $type = match ($request->input('command')) {
            '/출근' => AttendanceType::CheckIn,
            '/퇴근' => AttendanceType::CheckOut,
            default => null,
        };

        if ($type === null) {
            return response()->json([
                'response_type' => 'ephemeral',
                'text' => '지원하지 않는 명령어입니다.',
            ]);
        }

        $now = Carbon::now();

        if ($attendanceRecordService->isAlreadyRegistered($now->format('Y-m-d'), $type)) {
            return response()->json([
                'response_type' => 'ephemeral',
                'text' => sprintf('이미 %s 등록되어 있습니다.', $type === AttendanceType::CheckIn ? '출근' : '퇴근'),
            ]);
        }

        $values = $type === AttendanceType::CheckIn
            ? ['check_in_time' => $now->format('H:i')]
            : ['check_out_time' => $now->format('H:i')];

        $attendanceRecordService->upsert($now->format('Y-m-d'), $values);

        $slackNotifier->send($type === AttendanceType::CheckIn ? 'He is coming...' : 'He is gone...');

        return response()->json([
            'response_type' => 'ephemeral',
            'text' => sprintf('%s 등록되었습니다. (%s)', $type === AttendanceType::CheckIn ? '출근' : '퇴근', $now->format('H:i')),
        ]);
    }
}
