<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SocialNetwork extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'social_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
