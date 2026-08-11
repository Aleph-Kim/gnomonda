<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySlackSignature
{
    // 슬랙 슬래시 커맨드 요청 검증 (https://api.slack.com/authentication/verifying-requests-from-slack)
    public function handle(Request $request, Closure $next): Response
    {
        $signingSecret = config('services.slack.signing_secret');
        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        if (empty($signingSecret) || empty($timestamp) || empty($signature)) {
            abort(401);
        }

        // 재전송 공격 방지: 5분 이상 지난 요청은 거부
        if (abs(time() - (int) $timestamp) > 300) {
            abort(401);
        }

        $baseString = 'v0:'.$timestamp.':'.$request->getContent();
        $expectedSignature = 'v0='.hash_hmac('sha256', $baseString, $signingSecret);

        if (! hash_equals($expectedSignature, $signature)) {
            abort(401);
        }

        return $next($request);
    }
}
