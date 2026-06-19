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
            <div class="flex items-center justify-between gap-2 mb-5">
                <h2 class="text-sm font-semibold text-slate-700">مسار الطلب</h2>
                @if (($tracking['pathway'] ?? '') === 'civilian')
                    <span class="text-[11px] font-medium px-2 py-1 rounded-full bg-sky-50 text-sky-700 border border-sky-100">مسار مدني</span>
                @else
                    <span class="text-[11px] font-medium px-2 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-100">مسار عسكري</span>
                @endif
            </div>

            @php
                $steps = $tracking['steps'];
                $total = count($steps);
                $current = $tracking['current_index'];
                $progressPct = $total > 1 ? round(($current / ($total - 1)) * 100) : 0;
            @endphp

            <div class="mb-6">
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full transition-all duration-500"
                         style="width: {{ $progressPct }}%"></div>
                </div>
                <p class="text-xs text-slate-400 mt-2 text-center">{{ $progressPct }}% مكتمل</p>
            </div>

            <ol class="space-y-0">
                @foreach ($steps as $index => $step)
                    @php
                        $isDone = $step['status'] === 'done';
                        $isCurrent = $step['status'] === 'current';
                        $dotClass = $isDone ? 'bg-emerald-500 text-white' : ($isCurrent ? 'bg-emerald-100 text-emerald-700 ring-2 ring-emerald-500' : 'bg-slate-100 text-slate-400');
                        $lineClass = $isDone ? 'bg-emerald-400' : 'bg-slate-200';
                    @endphp
                    <li class="flex gap-3 {{ $index < $total - 1 ? 'pb-5' : '' }}">
                        <div class="flex flex-col items-center">
                            <span class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold shrink-0 {{ $dotClass }}">
                                @if ($isDone)
                                    ✓
                                @else
                                    {{ $index + 1 }}
                                @endif
                            </span>
                            @if ($index < $total - 1)
                                <span class="w-0.5 flex-1 mt-1 {{ $lineClass }} min-h-[24px]"></span>
                            @endif
                        </div>
                        <div class="pt-1 {{ $isCurrent ? '' : 'opacity-70' }}">
                            <p class="text-sm font-medium {{ $isCurrent ? 'text-emerald-800' : 'text-slate-700' }}">
                                {{ $step['label'] }}
                            </p>
                            @if ($isCurrent)
                                <p class="text-xs text-emerald-600 mt-0.5">← أنت هنا</p>
                            @elseif ($isDone)
                                <p class="text-xs text-slate-400 mt-0.5">مكتمل</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        <footer class="text-center mt-8 text-xs text-slate-400 leading-relaxed">
            <p>لا تُعرض تفاصيل مالية أو طبية على هذه الصفحة.</p>
            <p class="mt-1">للاستفسار تواصل مع مركز الاستقبال.</p>
        </footer>
    </div>
</body>
</html>
