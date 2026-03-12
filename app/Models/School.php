<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'state',
        'city',
        'street',
        'zipcode',
    ];

    // public function clubs(): HasMany
    // {
    //     return $this->hasMany(Club::class);
    // }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}