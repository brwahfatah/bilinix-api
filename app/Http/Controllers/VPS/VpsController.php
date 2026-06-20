<?php

namespace App\Http\Controllers\VPS;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vps\StoreVpsRequest;
use App\Services\VpsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class VpsController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly VpsService $vps) {}

    /**
     * GET /api/vps
     */
    public function index(Request $request): JsonResponse
    {
        $list = $this->vps->list($request->user());

        return $this->success(
            array_map(fn($dto) => $dto->toArray(), $list),
            'VPS list retrieved'
        );
    }

    /**
     * GET /api/vps/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $vps = $this->vps->get($request->user(), $id);
            return $this->success($vps->toArray(), 'VPS retrieved');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }

    /**
     * POST /api/vps
     */
    public function store(StoreVpsRequest $request): JsonResponse
    {
        try {
            $vps = $this->vps->create($request->user(), $request->validated());
            return $this->created($vps->toArray(), 'VPS provisioning started');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }

    /**
     * POST /api/vps/{id}/start
     */
    public function start(Request $request, int $id): JsonResponse
    {
        try {
            $vps = $this->vps->start($request->user(), $id);
            return $this->success($vps->toArray(), 'VPS started');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }

    /**
     * POST /api/vps/{id}/stop
     */
    public function stop(Request $request, int $id): JsonResponse
    {
        try {
            $vps = $this->vps->stop($request->user(), $id);
            return $this->success($vps->toArray(), 'VPS stopped');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }

    /**
     * POST /api/vps/{id}/reboot
     */
    public function reboot(Request $request, int $id): JsonResponse
    {
        try {
            $vps = $this->vps->reboot($request->user(), $id);
            return $this->success($vps->toArray(), 'VPS rebooted');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }

    /**
     * DELETE /api/vps/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->vps->terminate($request->user(), $id);
            return $this->noContent('VPS terminated');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }
}
