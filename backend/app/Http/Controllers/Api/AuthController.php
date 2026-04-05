<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = User::create($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => ['The email has already been taken.'],
            ]);
        }

        $user->refresh();

        Auth::guard('web')->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return $this->successResponse(new UserResource($user), status: 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::guard('web')->attempt($request->validated())) {
            return $this->errorResponse(
                [['message' => 'Invalid credentials']],
                401
            );
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return $this->successResponse(new UserResource(Auth::guard('web')->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->successResponse(['success' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(new UserResource($request->user()));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink($request->only('email'));

        return $this->successResponse(['success' => true]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->successResponse(['success' => true]);
        }

        return $this->errorResponse(
            [['message' => __($status)]],
            422
        );
    }
}
