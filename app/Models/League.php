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
    ];

    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }
}
