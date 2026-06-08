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
    ];

    protected $casts = [
        'time_slots' => 'array',
        'is_active' => 'boolean',
        'price_per_hour' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'amenities' => 'array',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
