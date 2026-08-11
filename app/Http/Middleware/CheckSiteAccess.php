<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSiteAccess
{
    // 세션에 비밀번호 인증 여부가 없으면 화면/API 조회를 차단
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('site_access_granted') === true) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(401);
        }

        return redirect()->route('access.form');
    }
}
