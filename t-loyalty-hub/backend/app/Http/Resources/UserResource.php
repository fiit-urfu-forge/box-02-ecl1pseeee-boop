<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'full_name' => $this->full_name,
            'financial_segment' => $this->financial_segment,
        ];
    }
}
