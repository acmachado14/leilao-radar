<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Billing\EntitlementResolver;
use App\Services\Mobile\UserAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request, UserAuthenticator $auth, EntitlementResolver $entitlements): JsonResponse
    {
        $payload = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ], [
            'name.required' => 'Informe seu nome.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Esse e-mail não parece válido.',
            'email.unique' => 'Não foi possível criar a conta. Confira os dados ou tente entrar.',
            'password.required' => 'Informe uma senha com pelo menos 8 caracteres.',
            'password.min' => 'A senha precisa ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ]);

        $issued = $auth->register($payload);

        return response()->json([
            'token' => $issued['token'],
            'user' => $this->userPayload($issued['user'], $entitlements),
        ], 201);
    }

    public function login(Request $request, UserAuthenticator $auth, EntitlementResolver $entitlements): JsonResponse
    {
        $payload = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        try {
            $issued = $auth->login($payload['email'], $payload['password']);
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->validator->errors()->first()], 422);
        }

        return response()->json([
            'token' => $issued['token'],
            'user' => $this->userPayload($issued['user'], $entitlements),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function forgotPassword(Request $request, UserAuthenticator $auth): JsonResponse
    {
        $payload = $request->validate(['email' => 'required|email']);
        $auth->sendResetLink($payload['email']);

        return response()->json(['ok' => true]);
    }

    public function me(Request $request, EntitlementResolver $entitlements): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user(), $entitlements)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload($user, EntitlementResolver $entitlements): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'entitlement' => $entitlements->snapshot($user),
        ];
    }
}
