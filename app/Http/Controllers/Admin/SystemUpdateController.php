<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SystemUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/** «تحديث النظام» — مدير النظام فقط، بكلمة المرور، وباكدج موقّع فقط. */
class SystemUpdateController extends Controller
{
    public function __construct(private readonly SystemUpdateService $updates) {}

    public function start(Request $request): JsonResponse
    {
        $user = $this->superAdmin();
        $data = $request->validate([
            'password' => ['required', 'string'],
            'size' => ['required', 'integer', 'min:1'],
            'name' => ['nullable', 'string', 'max:200'],
        ], ['password.required' => 'اكتب كلمة المرور للتأكيد.']);

        abort_unless(Hash::check($data['password'], $user->password), 422, 'كلمة المرور غير صحيحة.');

        return $this->attempt(fn () => response()->json([
            'id' => $this->updates->startUpload($user->id, (int) $data['size']),
            'chunk_bytes' => (int) config('system_update.chunk_bytes'),
        ]));
    }

    public function chunk(Request $request, string $id): JsonResponse
    {
        $user = $this->superAdmin();
        // الجزء بيتبعت كبيانات خام (مش ملف) — علشان حد upload_max_filesize (2M افتراضياً) ما يأثرش.
        $index = filter_var($request->query('index'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $bytes = (string) $request->getContent();
        if ($index === false || $bytes === '' || strlen($bytes) > (int) config('system_update.chunk_bytes')) {
            return response()->json(['message' => 'جزء غير صالح من ملف التحديث.'], 422);
        }

        return $this->attempt(fn () => response()->json([
            'received' => $this->updates->appendChunk($id, $user->id, (int) $index, $bytes),
        ]));
    }

    public function finish(string $id): JsonResponse
    {
        $user = $this->superAdmin();

        return $this->attempt(function () use ($id, $user) {
            $pkg = $this->updates->verify($id, $user->id);
            $current = $this->updates->currentVersion()['version'];

            AuditService::log(
                action: 'update',
                description: 'تحديث النظام من المتصفح — من '.($current ?: 'غير معروف').' إلى '.$pkg['version'].' (توقيع سليم، '.$pkg['files'].' ملف)',
                tag: 'admin',
                after: ['version' => $pkg['version'], 'commit' => $pkg['commit'], 'by' => $user->name],
            );

            $token = $this->updates->launch($pkg['root'], $pkg['version']);

            return response()->json([
                'message' => 'التوقيع سليم — جاري التحديث إلى '.$pkg['version'].'…',
                'version' => $pkg['version'],
                'status_url' => url('/system-update-status.php').'?t='.$token,
            ]);
        });
    }

    private function attempt(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function superAdmin(): User
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'تحديث النظام من صلاحيات مدير النظام فقط.');

        return $user;
    }
}
