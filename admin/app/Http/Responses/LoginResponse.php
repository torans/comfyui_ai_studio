<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): \Illuminate\Http\RedirectResponse
    {
        $request->session()->flash('status', '登录成功！');

        return redirect()->intended('/dashboard');
    }
}
