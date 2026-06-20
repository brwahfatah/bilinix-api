<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Domain;
use App\Models\Server;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use DB;

class GenerateRenewals extends Command
{
    protected $signature = 'billing:generate-renewals';
    protected $description = 'Generate renewal invoices';

    public function handle(): int
    {
        DB::transaction(function () {

            Domain::where('status','active')
                ->whereDate('next_due_date','<=', now()->addDays(7))
                ->each(function ($domain) {

                    if ($this->renewalExists('domain_renewal', $domain->id)) return;

                    $invoice = Invoice::create([
                        'user_id' => $domain->user_id,
                        'currency' => 'USD',
                        'status' => 'unpaid',
                        'amount' => 0,
                    ]);

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'type' => 'domain',
                        'service_id' => $domain->id,
                        'description' => 'domain_renewal',
                        'amount' => 10,
                    ]);

                    $invoice->recalculateTotal();
                });

            Server::where('status','active')
                ->whereDate('next_due_date','<=', now()->addDays(7))
                ->each(function ($server) {

                    if ($this->renewalExists('server_renewal', $server->id)) return;

                    $invoice = Invoice::create([
                        'user_id' => $server->user_id,
                        'currency' => 'USD',
                        'status' => 'unpaid',
                        'amount' => 0,
                    ]);

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'type' => 'server',
                        'service_id' => $server->id,
                        'description' => 'server_renewal',
                        'amount' => $server->plan->price,
                    ]);

                    $invoice->recalculateTotal();
                });
        });

        return Command::SUCCESS;
    }

    private function renewalExists(string $description, int $id): bool
    {
        return InvoiceItem::where('description', $description)
            ->where('service_id', $id)
            ->exists();
    }
}
