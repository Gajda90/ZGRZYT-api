<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\LogService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    /**
     * Obsługuje przychodzące żądanie uwierzytelnienia.
     */
    #[OA\Post(
        path: "/api/login",
        operationId: "loginUser",
        summary: "Logowanie użytkownika",
        description: "Uwierzytelnia użytkownika na podstawie loginu i hasła, a w odpowiedzi zwraca token dostępowy (Bearer Token) oraz rolę użytkownika. Ten endpoint jest publiczny i nie wymaga autoryzacji.",
        tags: ["Autoryzacja"],
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Dane logowania użytkownika.",
            content: new OA\JsonContent(
                required: ["login", "password"],
                properties: [
                    new OA\Property(property: "login", type: "string", example: "jkowalski"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Logowanie pomyślne. Zwraca token i rolę.",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "access_token", type: "string", example: "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ..."),
                        new OA\Property(property: "token_type", type: "string", example: "Bearer"),
                        new OA\Property(property: "role", type: "string", example: "user")
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Błąd uwierzytelniania (nieprawidłowe dane logowania)."
            ),
            new OA\Response(
                response: 422,
                description: "Błąd walidacji (np. brakujące pola)."
            )
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('login', 'password');

        $user = User::where('login', $credentials['login'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Nieprawidłowe dane logowania.'
            ], 401);
        }

        if (!$user->active) {
            return response()->json([
                'message' => 'Konto jest nieaktywne. Skontaktuj się z administratorem, aby je aktywować.'
            ], 403); // Forbidden
        }

        if ($user->banned_at) {
            return response()->json([
                'message' => 'Konto jest zbanowane. Skontaktuj się z administratorem.'
            ], 403);
        }

        Auth::login($user);
        
        if ($request->hasSession()) {
          $request->session()->regenerate();
        }
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'role' => $user->role,
        ]);
    }

    /**
     * Obsługuje przychodzące żądanie rejestracji.
     */
    #[OA\Post(
        path: "/api/register",
        operationId: "registerUser",
        summary: "Tworzenie nowego użytkownika przez Admina/IT",
        description: "Tworzy nowe konto użytkownika. Dostępne tylko dla uwierzytelnionych użytkowników z rolą 'admin' lub 'it'. Pozwala na zdefiniowanie roli nowego użytkownika.",
        tags: ["Autoryzacja"],
        security: [
            ["bearerAuth" => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "login", "email", "password", "password_confirmation", "role"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Jan Kowalski"),
                    new OA\Property(property: "login", type: "string", example: "jkowalski"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "j.kowalski@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "role", type: "string", enum: ["user", "it", "admin"], example: "user"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Użytkownik utworzony pomyślnie.",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "user", ref: "#/components/schemas/User")
                ])
            ),
            new OA\Response(response: 401, description: "Błąd autoryzacji."),
            new OA\Response(response: 403, description: "Brak uprawnień."),
            new OA\Response(response: 422, description: "Błąd walidacji danych."),
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $request->validate([
            'name' => 'required|string|max:255',
            'login' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:user,it,admin',
        ]);

        $user = User::create([
            'name' => $request->name,
            'login' => $request->login,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'active' => true,
        ]);

        $requestingUser = $request->user();
        \App\Models\Log::create([
            'user_id' => $requestingUser->id,
            'action' => 'create_user',
            'details' => 'Użytkownik '.$requestingUser->name.' utworzył nowe konto dla '.$user->name.' (ID: '.$user->id.') przez API.',
        ]);

        return response()->json([
            'message' => 'Użytkownik zarejestrowany pomyślnie.',
            'user' => $user
        ], 201);
    }

    /**
     * Rejestruje nowego użytkownika i tworzy zgłoszenie o aktywację.
     */
    #[OA\Post(
        path: "/api/request-account",
        operationId: "requestAccount",
        summary: "Zażądaj utworzenia nowego konta użytkownika",
        description: "Umożliwia zalogowanemu użytkownikowi wysłanie prośby o utworzenie nowego konta (np. dla nowego pracownika). Tworzy **nieaktywne** konto i automatycznie generuje zgłoszenie do działu IT z prośbą o jego aktywację.",
        tags: ["Autoryzacja"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "login", "email", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Anna Nowak"),
                    new OA\Property(property: "login", type: "string", example: "anowak"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "a.nowak@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "password123"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Prośba o konto została pomyślnie wysłana.",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "user", ref: "#/components/schemas/User")
                ])
            ),
            new OA\Response(response: 401, description: "Błąd autoryzacji."),
            new OA\Response(response: 403, description: "Brak uprawnień."),
            new OA\Response(response: 422, description: "Błąd walidacji danych (np. email już istnieje)."),
        ]
    )]
    public function requestAccount(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'login' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'login' => $request->login,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'active' => false,
        ]);

        $requestingUser = $request->user();

        \App\Models\Log::create([
            'user_id' => $requestingUser->id,
            'action' => 'request_user_account',
            'details' => 'Użytkownik '.$requestingUser->name.' zażądał utworzenia konta dla '.$user->name.' (ID: '.$user->id.').',
        ]);

        Ticket::create([
            'title' => 'Prośba o aktywację nowego konta: ' . $user->name,
            'description' => 'Użytkownik ' . $user->name . ' (' . $user->email . ') prosi o aktywację nowo założonego konta w systemie ZGRZYT. Prośba złożona przez: ' . $requestingUser->name . ' (ID: ' . $requestingUser->id . ').',
            'priority' => 'średni',
            'status' => 'nowe',
            'user_id' => $user->id,
        ]);

        return response()->json(['message' => 'Prośba o utworzenie konta została wysłana. Oczekuj na aktywację przez administratora.', 'user' => $user], 201);
    }

    /**
     * Odświeża token dostępowy.
     */
    #[OA\Post(
        path: "/api/refresh",
        operationId: "refreshToken",
        summary: "Odświeżanie tokena API",
        description: "Unieważnia obecny token (z którego wykonano żądanie) i zwraca nowy. Służy do przedłużania sesji klienta.",
        tags: ["Autoryzacja"],
        security: [
            ["bearerAuth" => []]
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Token pomyślnie odświeżony.",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "access_token", type: "string", example: "2|aBcDeFgHiJkLmNoPqRsTuVwXyZ..."),
                    new OA\Property(property: "token_type", type: "string", example: "Bearer"),
                    new OA\Property(property: "role", type: "string", example: "user")
                ])
            ),
            new OA\Response(response: 401, description: "Błąd autoryzacji (brak lub wygasły token).")
        ]
    )]
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->currentAccessToken()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'role' => $user->role,
        ]);
    }

    /**
     * Niszczy uwierzytelnioną sesję (unieważnia token).
     */
    #[OA\Post(
        path: "/api/logout",
        operationId: "logoutUser",
        summary: "Wylogowanie użytkownika (unieważnienie tokena API)",
        description: "Unieważnia token dostępowy API (Bearer token), z którym zostało wykonane żądanie. Jest to metoda wylogowania dla klientów API (np. aplikacji frontendowej Vue).",
        tags: ["Autoryzacja"],
        security: [
            ["bearerAuth" => []]
        ],
        responses: [
            new OA\Response(response: 200, description: "Token pomyślnie unieważniony.", content: new OA\JsonContent(properties: [new OA\Property(property: "message", type: "string")])),
            new OA\Response(response: 401, description: "Błąd autoryzacji (brak lub nieprawidłowy token).")
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Wylogowano pomyślnie.']);
    }
}
