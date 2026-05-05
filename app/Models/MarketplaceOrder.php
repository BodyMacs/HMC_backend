<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_id',
        'product_id',
        'quantity',
        'amount',
        'delivery_fee',
        'status',
        'transaction_reference',
        'seller_disbursement_status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
