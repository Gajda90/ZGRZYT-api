<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use OpenApi\Attributes as OA;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    /**
     * Wyświetla listę zasobów.
     */
    #[OA\Get(
        path: "/api/tickets",
        operationId: "listTickets",
        summary: "Wyświetla listę zgłoszeń",
        description: "Zwraca listę zgłoszeń. Użytkownicy z rolą 'user' widzą tylko swoje zgłoszenia. Użytkownicy 'it' i 'admin' widzą wszystkie.",
        tags: ["Zgłoszenia"],
        security: [
            ["bearerAuth" => []]
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista zgłoszeń.",
                content: new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/TicketFull"))
            ),
            new OA\Response(response: 401, description: "Błąd autoryzacji.")
        ]
    )]
    /**
     * Wyświetla listę zasobów.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = $user->role === 'user'
            ? $user->tickets()->with('assignedTo')
            : Ticket::with('user', 'assignedTo');

        if ($request->boolean('unassigned') && $user->role !== 'user') {
            $query->whereNull('assigned_it_id');
        }

        // Wyszukiwanie
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $searchTerm = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$searchTerm]);
            });
        }

        // Sortowanie
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $allowedSorts = ['created_at', 'status', 'priority', 'title'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->latest(); // domyślnie latest
        }

        if($request->boolean('noPagination') && $user->role !== 'user') {
            $tickets = $query->get();
            return response()->json($tickets);
        }

        // Paginacja
        $perPage = $request->get('per_page', 15);
        $tickets = $query->paginate($perPage);

        return response()->json($tickets);
    }

    /**
     * Zapisuje nowo utworzony zasób.
     */
    #[OA\Post(
        path: "/api/tickets",
        operationId: "createTicket",
        summary: "Zapisuje nowe zgłoszenie",
        description: "Tworzy nowe zgłoszenie w imieniu zalogowanego użytkownika.",
        tags: ["Zgłoszenia"],
        security: [
            ["bearerAuth" => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["title", "description"],
                properties: [
                    new OA\Property(property: "title", type: "string", maxLength: 255, example: "Drukarka nie działa"),
                    new OA\Property(property: "description", type: "string", example: "Drukarka w pokoju 101 nie drukuje, świeci się czerwona lampka."),
                    new OA\Property(property: "priority", type: "string", enum: ["niski", "średni", "wysoki"], example: "niski"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Zgłoszenie pomyślnie utworzone.",
                content: new OA\JsonContent(ref: "#/components/schemas/Ticket")
            ),
            new OA\Response(response: 422, description: "Błąd walidacji."),
            new OA\Response(response: 401, description: "Błąd autoryzacji.")
        ]
    )]
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'sometimes|in:niski,średni,wysoki',
        ]);

        $ticket = Ticket::create([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'],
            'priority' => $validatedData['priority'] ?? 'niski',
            'status' => 'nowe',
            'user_id' => Auth::id(),
        ]);

        return response()->json($ticket, 201);
    }

    /**
     * Wyświetla określony zasób.
     */
    #[OA\Get(
        path: "/api/tickets/{ticket}",
        operationId: "showTicket",
        summary: "Wyświetla określone zgłoszenie",
        description: "Pobiera szczegółowe informacje o pojedynczym zgłoszeniu, w tym dane zgłaszającego, przypisanego pracownika IT oraz historię wiadomości.",
        tags: ["Zgłoszenia"],
        security: [
            ["bearerAuth" => []]
        ],
        parameters: [
            new OA\Parameter(name: "ticket", in: "path", required: true, description: "ID zgłoszenia", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Szczegóły zgłoszenia.",
                content: new OA\JsonContent(ref: "#/components/schemas/TicketFull")
            ),
            new OA\Response(response: 401, description: "Błąd autoryzacji."),
            new OA\Response(response: 403, description: "Brak uprawnień do wyświetlenia tego zgłoszenia."),
            new OA\Response(response: 404, description: "Nie znaleziono zgłoszenia.")
        ]
    )]
    public function show(Ticket $ticket)
    {
        // Polityka autoryzacji sprawdzi, czy użytkownik ma prawo zobaczyć to zgłoszenie
        $this->authorize('view', $ticket);

        // Usprawnienie: Jawne załadowanie relacji z sortowaniem wiadomości.
        // Zapewnia, że wiadomości w zgłoszeniu będą zawsze uporządkowane od najnowszej.
        $ticket->load(['user', 'assignedTo', 'messages' => function ($query) {
            $query->with('sender')->latest();
        }]);

        return response()->json($ticket);
    }

    /**
     * Aktualizuje określony zasób.
     */
    #[OA\Put(
        path: "/api/tickets/{ticket}",
        operationId: "updateTicket",
        summary: "Aktualizuje określone zgłoszenie",
        description: "Aktualizuje status, priorytet lub przypisanie pracownika IT do zgłoszenia. Dostępne tylko dla ról 'it' i 'admin'.",
        tags: ["Zgłoszenia"],
        security: [
            ["bearerAuth" => []]
        ],
        parameters: [
            new OA\Parameter(name: "ticket", in: "path", required: true, description: "ID zgłoszenia", schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            description: "Pola do zaktualizowania.",
            content: new OA\JsonContent(properties: [
                new OA\Property(property: "status", type: "string", enum: ["nowe", "w trakcie", "zamknięte"]),
                new OA\Property(property: "priority", type: "string", enum: ["niski", "średni", "wysoki"]),
                new OA\Property(property: "assigned_it_id", type: "integer", nullable: true, description: "ID użytkownika (z rolą IT/Admin) do przypisania."),
            ])
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Zgłoszenie pomyślnie zaktualizowane.",
                content: new OA\JsonContent(ref: "#/components/schemas/Ticket")
            ),
            new OA\Response(response: 401, description: "Błąd autoryzacji."),
            new OA\Response(response: 403, description: "Brak uprawnień do aktualizacji tego zgłoszenia."),
            new OA\Response(response: 404, description: "Nie znaleziono zgłoszenia."),
            new OA\Response(response: 422, description: "Błąd walidacji.")
        ]
    )]
    public function update(Request $request, Ticket $ticket)
    {
        // Dostępne tylko dla 'IT' i 'Admin'
        $this->authorize('update', $ticket);

        $validatedData = $request->validate([
            'status' => 'sometimes|in:nowe,w trakcie,zamknięte',
            'priority' => 'sometimes|in:niski,średni,wysoki',
            'assigned_it_id' => 'sometimes|nullable|exists:users,id',
        ]);

        $ticket->update($validatedData);

        // TODO: Dodać logikę wysyłania powiadomień email przy zmianie statusu

        return response()->json($ticket);
    }

    /**
     * Usuwa określony zasób.
     */
    #[OA\Delete(
        path: "/api/tickets/{ticket}",
        operationId: "deleteTicket",
        summary: "Usuwa określone zgłoszenie",
        description: "Trwale usuwa zgłoszenie z systemu. Dostępne w zależności od zdefiniowanej polityki autoryzacji (prawdopodobnie dla admina).",
        tags: ["Zgłoszenia"],
        security: [
            ["bearerAuth" => []]
        ],
        parameters: [
            new OA\Parameter(name: "ticket", in: "path", required: true, description: "ID zgłoszenia", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Zgłoszenie pomyślnie usunięte."),
            new OA\Response(response: 401, description: "Błąd autoryzacji."),
            new OA\Response(response: 403, description: "Brak uprawnień do usunięcia tego zgłoszenia."),
            new OA\Response(response: 404, description: "Nie znaleziono zgłoszenia.")
        ]
    )]
    public function destroy(Ticket $ticket)
    {
        $this->authorize('delete', $ticket);

        $ticket->delete();

        return response()->json(null, 204);
    }
}
