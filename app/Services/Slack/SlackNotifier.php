<?php

namespace App\Services\Slack;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SlackNotifier
{
    private const ENDPOINT = 'https://slack.com/api/chat.postMessage';

    public function __construct(
        private readonly ?string $token,
        private readonly ?string $channel,
    ) {}

    public function send(string $text): void
    {
        if (empty($this->token) || empty($this->channel)) {
            // 키 미설정 환경(예: 로컬)에서도 앱이 죽지 않도록 무시
            return;
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(5)
                ->post(self::ENDPOINT, [
                    'channel' => $this->channel,
                    'text' => $text,
                ]);
        } catch (Throwable) {
            Log::error('SLACK NOTIFY ERROR');

            return;
        }

        if (! $response->successful() || ! $response->json('ok')) {
            Log::error('SLACK NOTIFY ERROR', ['status' => $response->status(), 'body' => $response->json()]);
        }
    }
}
