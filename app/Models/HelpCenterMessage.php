<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpCenterMessage extends Model
{
    use HasFactory;

    protected $table = 'help_center_messages';

    protected $fillable = [
        'user_id',
        'admin_id',
        'message',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
