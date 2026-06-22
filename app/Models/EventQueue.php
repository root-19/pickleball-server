<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventQueue extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'event_id', 'status', 'payment_status',
        'payment_method', 'paymongo_source_id', 'paymongo_payment_id',
        'paid_at', 'first_payer', 'joined_at',
    ];

    protected $casts = [
        'paid_at'     => 'datetime',
        'joined_at'   => 'datetime',
        'first_payer' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function event()
    {
        return $this->belongsTo(\App\Models\PickleEvent::class, 'event_id');
    }
}
