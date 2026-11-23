<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\StockLevel;
use App\Policies\ProductPolicy;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UsePolicy(ProductPolicy::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

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
        'status' => ProductStatus::Active->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'status' => ProductStatus::class,
        ];
    }

    /**
     * Case-insensitive match on name, SKU or description. "%" and "_" in the
     * search text are matched literally ("!" is the LIKE escape character,
     * which behaves the same on MySQL and SQLite).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $text): void
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text).'%';

        $query->where(function (Builder $where) use ($pattern): void {
            foreach (['name', 'sku', 'description'] as $column) {
                $where->orWhereRaw("{$column} like ? escape '!'", [$pattern]);
            }
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function taggedWith(Builder $query, int $tagId): void
    {
        $query->whereHas('tags', fn (Builder $tags) => $tags->where('tags.id', $tagId));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function stockLevel(Builder $query, StockLevel $level): void
    {
        match ($level) {
            StockLevel::In => $query->where('stock', '>', 0),
            StockLevel::Low => $this->lowStock($query),
            StockLevel::Out => $this->outOfStock($query),
        };
    }

    /**
     * 1 to LOW_STOCK_THRESHOLD units.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $query->whereBetween('stock', [1, self::LOW_STOCK_THRESHOLD]);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function outOfStock(Builder $query): void
    {
        $query->where('stock', 0);
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
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Root-relative browser URL for a photo path: uploads live on the
     * "public" disk (/storage/uploads/..., requires `php artisan
     * storage:link`); seeded demo photos are static files under public/images
     * (/images/products/coffee-mug.webp). Any other value, such as an
     * external URL or a path containing "..", has no URL.
     *
     * The URL has no scheme or host, so the browser loads the image from the
     * origin that served the page, which the CSP (img-src 'self') allows even
     * when that origin differs from APP_URL.
     */
    public static function photoUrlFor(mixed $photo): ?string
    {
        if (! is_string($photo) || str_contains($photo, '..')
            || ! preg_match('~^(uploads|images)/[A-Za-z0-9_./-]+$~', $photo)) {
            return null;
        }

        return str_starts_with($photo, 'uploads/') ? '/storage/'.$photo : '/'.$photo;
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => self::photoUrlFor($this->photo));
    }
}
