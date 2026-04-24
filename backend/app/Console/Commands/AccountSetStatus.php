<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class AccountSetStatus extends Command
{
    protected $signature = 'digitalbank:account:set-status
                            {id : Account UUID}
                            {status : active|frozen|closed}
                            {--reason= : Human-readable reason for audit_log}';

    protected $description = 'Toggle an account status (active / frozen / closed). MVP admin tool.';

    public function handle(AuditLogger $audit): int
    {
        $valid = [Account::STATUS_ACTIVE, Account::STATUS_FROZEN, Account::STATUS_CLOSED];
        $status = (string) $this->argument('status');
        if (! in_array($status, $valid, true)) {
            $this->error('Status must be one of: '.implode(', ', $valid));
            return self::FAILURE;
        }

        /** @var Account|null $account */
        $account = Account::query()->find((string) $this->argument('id'));
        if ($account === null) {
            $this->error('Account not found.');
            return self::FAILURE;
        }

        $old = $account->status;
        $account->status = $status;
        $account->save();

        $audit->record(
            action: "account.{$status}",
            entityType: 'account',
            entityId: $account->id,
            userId: $account->user_id,
            old: ['status' => $old],
            new: ['status' => $status, 'reason' => $this->option('reason')],
        );

        $this->info("Account {$account->id} status: $old → $status");
        return self::SUCCESS;
    }
}
