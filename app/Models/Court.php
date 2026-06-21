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
        'location',
        'price_per_hour',
        'court_image',
        'time_slots',
        'is_active',
        'close_reason',
        'latitude',
        'longitude',
        'amenities',
        'about',
        'court_quality',
        'has_tent',
        'venue_type',
        'parking_slots',
    ];

    protected $casts = [
        'time_slots' => 'array',
        'is_active' => 'boolean',
        'price_per_hour' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'amenities' => 'array',
        'has_tent' => 'boolean',
        'parking_slots' => 'integer',
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
