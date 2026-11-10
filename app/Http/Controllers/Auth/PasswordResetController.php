<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user && $user->hasVerifiedEmail() && $user->is_active) {
            Password::sendResetLink(['email' => $validated['email']]);
        }

        return back()->with('status', 'إذا كان البريد مسجلًا ومؤكدًا والحساب نشطًا، فستصلك رسالة برابط إعادة تعيين كلمة المرور.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! $user->hasVerifiedEmail() || ! $user->is_active) {
            return back()->withErrors(['email' => 'تعذر إعادة التعيين. تأكد من البريد وتأكيده وأن الحساب نشط.']);
        }

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'تم تغيير كلمة المرور بنجاح. سجل الدخول بكلمة المرور الجديدة.')
            : back()->withErrors(['email' => 'رابط إعادة التعيين غير صالح أو انتهت صلاحيته.']);
    }
}
