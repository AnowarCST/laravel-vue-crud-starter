<?php

namespace App\Models;

use App\Policies\TagPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

#[UsePolicy(TagPolicy::class)]
class Tag extends Model
{
    protected $fillable = ['name'];
}
