<?php

namespace App\Services;

use App\Models\Server;
use App\Models\InvoiceItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ServiceLifecycleManager
{
    /* =========================
     * CREATE SERVER (ORDER)
     * ========================= */
    public function createServer(InvoiceItem $item): Server
{
    $data = is_array($item->reference_data)
        ? $item->reference_data
        : json_decode($item->reference_data, true);

    if (!$data || !is_array($data)) {
        throw ValidationException::withMessages([
            'reference_data' => 'Invalid server configuration format'
        ]);
    }

    // Ensure period exists
    $data['period'] = $data['period'] ?? $item->period ?? 1;

    // Ensure hostname/name exists
    $name = $data['hostname'] ?? 'VPS-' . strtoupper(str()->random(6));

    // Validate required fields
    $this->validateServerConfig($data);

    // Insert into servers table
    return Server::create([
        'user_id'        => $item->invoice->user_id,
        'invoice_id'     => $item->invoice_id,
        'server_plan_id' => $data['plan_id'],

        'name'     => $name,
        'provider' => 'local',

        'os' => $data['os'], // Required column

        'config' => [
            'os'       => $data['os'],
            'location' => $data['location'],
            'ipv6'     => (bool) ($data['ipv6'] ?? false),
        ],

        'period'        => $data['period'],
        'next_due_date' => now()->addMonths($data['period']),

        'status' => 'pending',
    ]);
}

    /* ========================= */
    public function activateServer(Server $server): void
    {
        if (!in_array($server->status, ['pending', 'awaiting_approval', 'suspended'])) {
            return;
        }

        $server->update([
            'status'        => 'active',
            'activated_at'  => now(),
            'next_due_date' => $server->next_due_date ?? now()->addMonths($server->period),
        ]);
    }

    /* ========================= */
    public function provision(Server $server): void
    {
        if ($server->status !== 'pending') return;

        $server->update(['status' => 'provisioning']);

        try {
            // Simulated provider
            $server->update([
                'ip_address'     => '192.0.2.' . rand(10, 250),
                'ssh_username'   => 'root',
                'ssh_password'   => bcrypt(str()->random(16)),
                'provisioned_at' => now(),
            ]);

            $this->activateServer($server);

        } catch (\Throwable $e) {
            $this->fail($server, $e->getMessage());
        }
    }

    /* ========================= */
    public function renew(Server $server): void
    {
        $server->update([
            'next_due_date' => Carbon::parse($server->next_due_date)
                ->addMonths($server->period),
        ]);
    }

    public function suspend(Server $server): void
    {
        if ($server->status === 'active') {
            $server->update(['status' => 'suspended']);
        }
    }

    public function terminate(Server $server): void
    {
        $server->update(['status' => 'terminated']);
    }

    public function fail(Server $server, string $reason): void
    {
        Log::error('Server provisioning failed', [
            'server_id' => $server->id,
            'reason'    => $reason,
        ]);

        $server->update([
            'status'     => 'failed',
            'last_error' => $reason,
        ]);
    }

    /* ========================= */
    protected function validateServerConfig(array $data): void
    {
        $required = ['plan_id', 'period', 'os', 'location'];

        foreach ($required as $key) {
            if (!array_key_exists($key, $data)) {
                throw ValidationException::withMessages([
                    "config.$key" => "Missing server config value: $key"
                ]);
            }
        }
    }
}
