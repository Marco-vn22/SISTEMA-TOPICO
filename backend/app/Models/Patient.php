<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['allergies' => 'encrypted', 'background' => 'encrypted'];
    }
}
