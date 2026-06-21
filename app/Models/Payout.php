<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'payout_account_id',
        'gross_amount',
        'fee_amount',
        'net_amount',
        'status',
        'reference',
        'admin_note',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'gross_amount'  => 'decimal:2',
        'fee_amount'    => 'decimal:2',
        'net_amount'    => 'decimal:2',
        'processed_at'  => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function payoutAccount()
    {
        return $this->belongsTo(PayoutAccount::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
