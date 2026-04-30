<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'partner_name',
        'short_description',
        'logo_url',
        'brand_color_hex',
        'cashback_percent',
        'financial_segment',
    ];

    public $timestamps = false;

    protected $casts = [
        'cashback_percent' => 'decimal:2',
    ];
}
