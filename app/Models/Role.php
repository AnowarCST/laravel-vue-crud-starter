<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Role extends Model
{
    protected $fillable = [
        'name', 'description'
    ];

    protected $table = 'roles';

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
