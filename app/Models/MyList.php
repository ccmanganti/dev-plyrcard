<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MyList extends Model
{
    protected $fillable = [
        'user_id', 'name', 'slug', 'color', 'is_system', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'sort_order' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(MyListSchool::class);
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'my_list_schools', 'my_list_id', 'school_id')
            ->withTimestamps();
    }
}
