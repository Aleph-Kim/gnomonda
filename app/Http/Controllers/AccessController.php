<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessController extends Controller
{
    public function form(): View
    {
        return view('access.form');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required|string']);

        $password = config('services.access.password');

        if (empty($password) || ! hash_equals((string) $password, $request->input('password'))) {
            return back()->withErrors(['password' => '비밀번호가 올바르지 않습니다.']);
        }

        $request->session()->put('site_access_granted', true);
        $request->session()->regenerate();

        return redirect()->route('home');
    }
}
