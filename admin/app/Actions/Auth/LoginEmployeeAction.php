<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginEmployeeAction
{
    /**
     * Authenticate an employee and create a Sanctum token.
     *
     * @throws ValidationException
     */
    public function handle(string $email, string $password): array
    {
        $user = User::where('email', $email)
            ->where('role', 'employee')
            ->where('status', 'active')
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['账号或密码错误。'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return [
            'token' => $user->createToken('tauri-client')->plainTextToken,
            'user' => $user->only(['id', 'name', 'email', 'role']),
        ];
    }
}
