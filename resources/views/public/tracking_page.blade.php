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
    <div class="mx-auto max-w-md px-4 py-8">
        <header class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-600 text-white text-2xl shadow-lg mb-3">🦿</div>
            <h1 class="text-xl font-bold text-slate-900">متابعة حالة الطلب</h1>
            <p class="text-sm text-slate-500 mt-1">مركز الأطراف الصناعية</p>
        </header>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 mb-6">
            <p class="text-xs text-slate-400 mb-1">الحالة الحالية</p>
            <p class="text-lg font-bold text-emerald-700">{{ $tracking['stage_label'] }}</p>
            <p class="text-xs text-slate-400 mt-3 font-mono" dir="ltr">{{ $tracking['tracking_uid'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <p class="text-sm font-semibold text-slate-700 mb-4 text-center">حالة الطلب</p>
            <ul class="space-y-3">
                @foreach ($tracking['steps'] ?? [] as $index => $step)
                    @php
                        $status = $step['status'] ?? 'pending';
                        $isDone = $status === 'done';
                        $isCurrent = $status === 'current';
                    @endphp
                    <li class="flex items-start gap-3 {{ $isCurrent ? '' : ($isDone ? 'opacity-90' : 'opacity-45') }}">
                        <span @class([
                            'flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold',
                            'bg-emerald-600 text-white' => $isCurrent,
                            'bg-emerald-100 text-emerald-700' => $isDone,
                            'bg-slate-100 text-slate-400' => ! $isDone && ! $isCurrent,
                        ])>{{ $isDone ? '✓' : ($index + 1) }}</span>
                        <div class="min-w-0">
                            <p @class([
                                'text-sm font-medium',
                                'text-emerald-700' => $isCurrent,
                                'text-slate-700' => ! $isCurrent,
                            ])>{{ $step['label'] }}</p>
                            @if ($isCurrent)
                                <p class="text-xs text-emerald-600 mt-0.5">المرحلة الحالية</p>
                            @elseif ($isDone)
                                <p class="text-xs text-slate-400 mt-0.5">مكتمل</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        <footer class="text-center mt-8 text-xs text-slate-400 leading-relaxed">
            <p>لا تُعرض تفاصيل مالية أو طبية على هذه الصفحة.</p>
            <p class="mt-1">للاستفسار تواصل مع مركز الاستقبال.</p>
        </footer>
    </div>
</body>
</html>
