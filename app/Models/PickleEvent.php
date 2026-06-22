<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PickleEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'location', 'event_image', 'event_date',
        'open_time', 'close_time', 'max_players', 'price_per_head',
        'rules', 'about', 'latitude', 'longitude', 'is_active',
    ];

    protected $casts = [
        'event_date'    => 'date:Y-m-d',
        'is_active'     => 'boolean',
        'price_per_head'=> 'float',
        'max_players'   => 'integer',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
