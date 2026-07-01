<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>متابعة الطلب — مركز الأطراف الصناعية</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-b from-emerald-50 to-white text-slate-800">
    @php
        $percent = (int) ($tracking['progress_percent'] ?? 0);
    @endphp
    <div class="mx-auto max-w-md px-4 py-8">
        <header class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-600 text-white text-2xl shadow-lg mb-3">🦿</div>
            <h1 class="text-xl font-bold text-slate-900">متابعة حالة الطلب</h1>
            <p class="text-sm text-slate-500 mt-1">مركز الأطراف الصناعية</p>
        </header>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <p class="text-sm font-semibold text-slate-700 mb-4 text-center">حالة الطلب</p>

            <div
                class="h-3 w-full rounded-full bg-slate-100 overflow-hidden mb-3"
                role="progressbar"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="{{ $percent }}"
                aria-label="نسبة إنجاز الطلب"
            >
                <div
                    class="h-full rounded-full bg-gradient-to-l from-emerald-500 to-emerald-600 transition-all duration-500"
                    style="width: {{ $percent }}%"
                ></div>
            </div>

            <p class="text-center text-3xl font-bold text-emerald-700 tabular-nums">{{ $percent }}%</p>
        </div>

        <p class="text-center mt-6 text-xs text-slate-400 font-mono" dir="ltr">{{ $tracking['tracking_uid'] }}</p>
    </div>
</body>
</html>
