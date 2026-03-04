<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckPendingTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-pending-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check status of pending NotchPay transactions and update them';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\NotchPayService $notchPayService)
    {
        $this->info('Starting check of pending transactions...');

        // On vérifie les transactions en attente créées dans les dernières 24h
        $pendingTransactions = \App\Models\Transaction::where('status', 'pending')
            ->where('created_at', '>', now()->subDay())
            ->get();

        if ($pendingTransactions->isEmpty()) {
            $this->info('No pending transactions found.');
            return;
        }

        $this->info("Found {$pendingTransactions->count()} pending transactions.");

        foreach ($pendingTransactions as $transaction) {
            $this->info("Checking transaction: {$transaction->reference}");

            $status = $notchPayService->checkAndFulfillTransaction($transaction->reference);

            if ($status === 'reference_not_found') {
                $this->warn("Result for {$transaction->reference}: NOT FOUND in NotchPay (Reference invalid)");
            } elseif ($status === 'error') {
                $this->error("Result for {$transaction->reference}: API ERROR during check");
            } else {
                $this->line("Result for {$transaction->reference}: {$status}");
            }
        }

        $this->info('Check completed.');
    }
}
