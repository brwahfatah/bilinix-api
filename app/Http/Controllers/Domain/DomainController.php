<?php

namespace App\Http\Controllers\Domain;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\UpdateNameserversRequest;
use App\Services\DomainService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DomainController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly DomainService $domain) {}

    /**
     * GET /api/domains
     */
    public function index(Request $request): JsonResponse
    {
        $list = $this->domain->list($request->user());

        return $this->success(
            array_map(fn($dto) => $dto->toArray(), $list),
            'Domains retrieved'
        );
    }

    /**
     * GET /api/domains/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $domain = $this->domain->get($request->user(), $id);
            return $this->success($domain->toArray(), 'Domain retrieved');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }

    /**
     * POST /api/domains/{id}/renew
     */
    public function renew(Request $request, int $id): JsonResponse
    {
        try {
            $domain = $this->domain->renew($request->user(), $id);
            return $this->success($domain->toArray(), 'Domain renewed successfully');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }

    /**
     * POST /api/domains/{id}/auto-renew
     */
    public function toggleAutoRenew(Request $request, int $id): JsonResponse
    {
        try {
            $domain = $this->domain->toggleAutoRenew($request->user(), $id);
            $state  = $domain->autoRenew ? 'enabled' : 'disabled';
            return $this->success($domain->toArray(), "Auto-renew {$state}");
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }

    /**
     * POST /api/domains/{id}/lock
     */
    public function lock(Request $request, int $id): JsonResponse
    {
        try {
            $domain = $this->domain->lock($request->user(), $id);
            return $this->success($domain->toArray(), 'Domain locked');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }

    /**
     * POST /api/domains/{id}/unlock
     */
    public function unlock(Request $request, int $id): JsonResponse
    {
        try {
            $domain = $this->domain->unlock($request->user(), $id);
            return $this->success($domain->toArray(), 'Domain unlocked');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }

    /**
     * POST /api/domains/search
     * Public — no auth required. Checks domain availability for one or more TLDs.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sld'    => 'required|string|max:63',
            'tlds'   => 'required|array|min:1',
            'tlds.*' => 'required|string',
        ]);

        $results = $this->domain->search($validated['sld'], $validated['tlds']);

        return $this->success(['results' => $results], 'Domain search results');
    }

    /**
     * PATCH /api/domains/{id}/nameservers
     */
    public function updateNameservers(UpdateNameserversRequest $request, int $id): JsonResponse
    {
        try {
            $domain = $this->domain->updateNameservers(
                $request->user(),
                $id,
                $request->validated('nameservers'),
            );
            return $this->success($domain->toArray(), 'Nameservers updated');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }
}
