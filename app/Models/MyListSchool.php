<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class MyListSchool extends Pivot
{
    protected $table = 'my_list_schools';

    public $incrementing = true;

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
