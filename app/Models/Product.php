<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = ['active', 'draft', 'archived'];

    /**
     * Products with this many units or fewer (but more than zero) are
     * reported as "low stock".
     */
    public const LOW_STOCK_THRESHOLD = 5;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'sku', 'description', 'price', 'stock', 'status', 'category_id', 'photo',
    ];

    /**
     * Defaults for new models, matching the column defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'stock' => 0,
        'status' => 'active',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['photo_url'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->select(['name as text', 'id']);
    }

    /**
     * Browser URL for the photo: uploads live on the "public" disk
     * (/storage/uploads/..., requires `php artisan storage:link`); seeded
     * demo photos are static files under public/images (for example
     * images/products/coffee-mug.webp). Any other value, such as an external
     * URL or a path containing "..", has no URL.
     *
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $photo = $this->photo;

            if (! is_string($photo) || str_contains($photo, '..')
                || ! preg_match('~^(uploads|images)/[A-Za-z0-9_./-]+$~', $photo)) {
                return null;
            }

            return str_starts_with($photo, 'uploads/')
                ? Storage::disk('public')->url($photo)
                : asset($photo);
        });
    }
}
