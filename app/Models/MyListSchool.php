<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MyListSchool extends Model
{
    protected $fillable = ['my_list_id', 'school_id'];

    public function list(): BelongsTo
    {
        return $this->belongsTo(MyList::class, 'my_list_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
