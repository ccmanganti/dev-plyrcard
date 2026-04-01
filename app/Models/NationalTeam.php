<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NationalTeam extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'logo',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}