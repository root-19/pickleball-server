<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class StaffAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'username',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
