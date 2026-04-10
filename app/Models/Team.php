<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'name',
        'logo',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}