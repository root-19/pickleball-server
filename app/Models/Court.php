<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'court_type',
        'price_per_hour',
        'court_image',
        'is_active',
        'close_reason',
        'about',
        'court_quality',
        'has_tent',
        'venue_type',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price_per_hour' => 'decimal:2',
        'has_tent' => 'boolean',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bookings()
    {
        return $this->hasMany(\App\Models\Booking::class);
    }

    public function reviews()
    {
        return $this->hasManyThrough(\App\Models\BookingReview::class, \App\Models\Booking::class);
    }
}
