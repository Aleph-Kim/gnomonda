<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>출퇴근 기록 - {{ config('app.name', 'Laravel') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/attendance-calendar.js'])
    </head>
    <body class="bg-[#F6F6F7] text-[#1A1A1A] min-h-screen">
        <div id="attendance-calendar-root" class="max-w-3xl mx-auto p-6"></div>
    </body>
</html>
