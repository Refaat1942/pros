<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * تبديل سريع بين حسابات الأدوار المزروعة — بيئة local فقط.
 */
class QuickRoleSwitcherController extends Controller
{
    public function switch(Request $request, string $role): RedirectResponse
    {
        if (! app()->environment('local')) {
            abort(404);
        }

        $roles = config('dev-role-switcher.roles', []);

        if (! array_key_exists($role, $roles)) {
            abort(404);
        }

        $user = User::query()
            ->where('email', "{$role}@clinic.com")
            ->where('status', User::STATUS_ACTIVE)
            ->firstOrFail();

        Auth::logout();

        Auth::loginUsingId($user->id);

        $request->session()->regenerate();

        return redirect()->route($roles[$role]['route']);
    }
}
