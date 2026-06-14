<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FavoriteCourt extends Model
{
    protected $fillable = ['user_id', 'court_id'];

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
