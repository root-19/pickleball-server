<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'account_name',
        'account_number',
        'card_holder',
        'card_last_four',
        'card_expiry',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
