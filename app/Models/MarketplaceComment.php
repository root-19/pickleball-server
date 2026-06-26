<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceComment extends Model
{
    use HasFactory;

    protected $table = 'marketplace_post_comments';

    protected $fillable = [
        'post_id',
        'user_id',
        'comment',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function post()
    {
        return $this->belongsTo(MarketplacePost::class, 'post_id');
    }
}
