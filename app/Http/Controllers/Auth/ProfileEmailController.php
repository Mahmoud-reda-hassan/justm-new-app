<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ProfileEmailController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.profile-email', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required', 'current_password'],
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل.',
            'current_password.current_password' => 'كلمة المرور الحالية غير صحيحة.',
        ]);

        if ($user->email !== $validated['email']) {
            $user->forceFill([
                'email' => $validated['email'],
                'email_verified_at' => null,
            ])->save();
        }

        if ($user->hasVerifiedEmail()) {
            return back()->with('status', 'البريد الإلكتروني مؤكد بالفعل.');
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('warning', 'تم حفظ البريد، لكن تعذر إرسال رسالة التحقق. راجع إعدادات البريد وحاول مرة أخرى.');
        }

        return back()->with('status', 'تم حفظ البريد وإرسال رابط التحقق. افتح الرسالة لتأكيده.');
    }
}
