<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplacePost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'price',
        'link',
        'image',
        'video',
        'views',
        'hearts',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function views()
    {
        return $this->belongsToMany(User::class, 'marketplace_post_views', 'post_id', 'user_id')->withTimestamps();
    }

    public function comments()
    {
        return $this->hasMany(MarketplaceComment::class, 'post_id');
    }
}
