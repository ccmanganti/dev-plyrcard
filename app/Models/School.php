<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Club;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;



class School extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'state',
        'city',
        'street',
        'zip',
    ];

    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }

    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, Club::class);
    }
}
