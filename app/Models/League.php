<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'gender',
        'sport',
        'logo',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clubs()
    {
        return $this->hasMany(Club::class);
    }
}