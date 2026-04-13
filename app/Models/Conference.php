<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conference extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
    ];

    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }
}