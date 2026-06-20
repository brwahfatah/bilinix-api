<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\CreateTicketRequest;
use App\Http\Requests\Ticket\ReplyTicketRequest;
use App\Services\TicketService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TicketController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TicketService $ticket) {}

    /**
     * GET /api/tickets
     */
    public function index(Request $request): JsonResponse
    {
        $list = $this->ticket->list($request->user());

        return $this->success(
            array_map(fn($dto) => $dto->toArray(), $list),
            'Tickets retrieved'
        );
    }

    /**
     * GET /api/tickets/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $ticket = $this->ticket->get($request->user(), $id);
            return $this->success($ticket->toArray(), 'Ticket retrieved');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }

    /**
     * POST /api/tickets
     */
    public function store(CreateTicketRequest $request): JsonResponse
    {
        try {
            $ticket = $this->ticket->create($request->user(), $request->validated());
            return $this->created($ticket->toArray(), 'Ticket created');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }

    /**
     * POST /api/tickets/{id}/reply
     */
    public function reply(ReplyTicketRequest $request, int $id): JsonResponse
    {
        try {
            $ticket = $this->ticket->reply(
                $request->user(),
                $id,
                $request->validated('message'),
            );
            return $this->success($ticket->toArray(), 'Reply posted');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }

    /**
     * POST /api/tickets/{id}/close
     */
    public function close(Request $request, int $id): JsonResponse
    {
        try {
            $this->ticket->close($request->user(), $id);
            return $this->noContent('Ticket closed');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }
}
