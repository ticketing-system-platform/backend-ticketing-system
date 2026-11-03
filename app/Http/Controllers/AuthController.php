<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        // Pendaftar baru selalu menjadi customer
        $user = User::create([...$request->validated(), 'role' => 'customer']);

        $token = $user->createToken('auth')->plainTextToken;

        return (new UserResource($user))
            ->additional(['token' => $token])
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah',
            ]);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return (new UserResource($user))->additional(['token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Berhasil logout']);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }
}
