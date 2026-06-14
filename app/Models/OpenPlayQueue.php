<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpenPlayQueue extends Model
{
    protected $fillable = [
        'user_id',
        'court_id',
        'booking_date',
        'time_slot_start',
        'time_slot_end',
        'status',
        'matched_with',
        'payment_status',
        'payment_deadline',
        'paid_at',
        'slot_opener',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'time_slot_start' => 'datetime',
        'time_slot_end' => 'datetime',
        'joined_at' => 'datetime',
        'matched_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function matchedUser()
    {
        return $this->belongsTo(User::class, 'matched_with');
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }
}
