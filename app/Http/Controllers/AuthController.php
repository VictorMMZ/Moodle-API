<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // Procesar login del usuario
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt([...$credentials, 'is_active' => true])) {
            return response()->json(['message' => 'Las credenciales no son válidas'], 401);
        }

       $request->session()->regenerateToken();
        $request->user()->update(['last_login_at' => now()]);

        return response()->json(['message' => 'Login correcto', 'user' => $request->user()]);
    }

    // Logout del usuario
    public function logout(Request $request): JsonResponse
    {
       Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    // Obtener el usuario autenticado
    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}