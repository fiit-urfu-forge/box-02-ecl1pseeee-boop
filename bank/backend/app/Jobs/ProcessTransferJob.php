<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\TransferService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableTrait;

class ProcessTransferJob implements ShouldQueue
{
    use QueueableTrait, Queueable;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(public readonly string $transactionId)
    {
        $this->onQueue('transfers');
    }

    public function handle(TransferService $service): void
    {
        /** @var Transaction|null $tx */
        $tx = Transaction::query()->find($this->transactionId);
        if ($tx === null) {
            return;
        }
        $service->execute($tx);
    }
}
