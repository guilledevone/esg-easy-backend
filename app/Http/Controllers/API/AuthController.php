<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // REGISTRO
    public function register(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:profiles',
            'password' => 'required|min:6',
            'company' => 'required|string'
        ]);

        $profile = Profile::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'referral_code' => Str::random(8)
        ]);

        $token = $profile->createToken('esg-token')->plainTextToken;

        return response()->json([
            'message' => 'Registro exitoso',
            'token' => $token,
            'profile' => $profile
        ], 201);
    }

    // LOGIN
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $profile = Profile::where('email', $request->email)->first();

        if (!$profile || !Hash::check($request->password, $profile->password)) {
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }

        $token = $profile->createToken('esg-token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'token' => $token,
            'profile' => $profile
        ]);
    }

    // LOGOUT
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete;
        return response()->json(['message' => 'Logout exitoso']);
    }
}
