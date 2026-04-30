<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'transaction_id' => (int) $this->transaction_id,
            'account_id' => (int) $this->account_id,
            'cashback_amount' => (float) $this->cashback_amount,
            'payout_date' => $this->payout_date?->toDateString(),
            'program' => $this->whenLoaded('account', function () {
                $program = $this->account?->loyaltyProgram;
                return $program ? [
                    'loyalty_program_id' => (int) $program->loyalty_program_id,
                    'loyalty_program_name' => $program->loyalty_program_name,
                    'cashback_currency' => $program->cashback_currency,
                ] : null;
            }),
        ];
    }
}
