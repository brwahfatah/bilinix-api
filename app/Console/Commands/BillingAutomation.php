<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\Domain;
use App\Models\Server;
use Carbon\Carbon;

class BillingAutomation extends Command
{
    protected $signature = 'billing:automation';
    protected $description = 'Suspend and terminate overdue services';

    public function handle(): int
    {
        $this->info('Running billing automation...');

        // =====================
        // SUSPEND AFTER 7 DAYS
        // =====================
        Invoice::where('status', 'unpaid')
            ->whereDate('created_at', '<=', now()->subDays(7))
            ->each(function ($invoice) {

                foreach ($invoice->items as $item) {

                    if ($item->type === 'domain') {
                        Domain::whereId($item->service_id)
                            ->where('status', 'active')
                            ->update(['status' => 'suspended']);
                    }

                    if ($item->type === 'server') {
                        Server::whereId($item->service_id)
                            ->where('status', 'active')
                            ->update(['status' => 'suspended']);
                    }
                }
            });

        // =====================
        // TERMINATE AFTER 30 DAYS
        // =====================
        Invoice::where('status', 'unpaid')
            ->whereDate('created_at', '<=', now()->subDays(30))
            ->each(function ($invoice) {

                foreach ($invoice->items as $item) {

                    if ($item->type === 'domain') {
                        Domain::whereId($item->service_id)
                            ->update(['status' => 'expired']);
                    }

                    if ($item->type === 'server') {
                        Server::whereId($item->service_id)
                            ->update(['status' => 'terminated']);
                    }
                }

                $invoice->update(['status' => 'cancelled']);
            });

        $this->info('Billing automation completed.');

        return Command::SUCCESS;
    }
}
