<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'partner_id' => (int) $this->partner_id,
            'partner_name' => $this->partner_name,
            'short_description' => $this->short_description,
            'logo_url' => $this->logo_url,
            'brand_color_hex' => $this->brand_color_hex,
            'cashback_percent' => (float) $this->cashback_percent,
            'financial_segment' => $this->financial_segment,
        ];
    }
}
