<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;


class Website extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'light_color',
        'dark_color',
        'project_json',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
