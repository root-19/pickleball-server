<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerificationAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'id_image',
        'court_image_1',
        'court_image_2',
        'court_image_3',
        'facebook',
        'instagram',
        'tiktok',
        'website',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
