<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>비밀번호 확인 - {{ config('app.name', 'Laravel') }}</title>

        @fonts

        @vite(['resources/css/app.css'])
    </head>
    <body class="bg-[#F6F6F7] text-[#1A1A1A] min-h-screen flex items-center justify-center p-6">
        <form method="POST" action="{{ route('access.verify') }}" class="w-full max-w-sm bg-white rounded-2xl shadow-sm p-8 space-y-4">
            @csrf

            <h1 class="text-lg font-semibold">비밀번호를 입력해주세요</h1>

            <div class="space-y-1">
                <input
                    type="password"
                    name="password"
                    autofocus
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-400"
                    placeholder="비밀번호"
                >
                @error('password')
                    <p class="text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-[#1A1A1A] text-white py-2 font-medium hover:opacity-90"
            >
                확인
            </button>
        </form>
    </body>
</html>
