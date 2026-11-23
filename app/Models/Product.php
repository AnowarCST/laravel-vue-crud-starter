<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'description', 'price', 'category_id', 'photo',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['photo_url'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class)->select(['name as text', 'id']);
    }

    /**
     * Browser URL for the photo: uploads live on the "public" disk
     * (/storage/uploads/..., requires `php artisan storage:link`); seeded
     * demo photos are static files under public/images.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $photo = $this->photo;

            if (! is_string($photo) || $photo === '') {
                return null;
            }

            return str_starts_with($photo, 'uploads/')
                ? Storage::disk('public')->url($photo)
                : asset($photo);
        });
    }
}
