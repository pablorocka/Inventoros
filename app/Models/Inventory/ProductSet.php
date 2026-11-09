<?php

namespace App\Models\Inventory;

use App\Models\Auth\Organization;
use App\Support\HtmlSanitizer;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductSet extends Model
{
    use SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'instructions',
    ];

    /**
     * Instructions are rendered as HTML, so they are always sanitized on the way in.
     */
    protected function instructions(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => HtmlSanitizer::clean($value),
        );
    }

    /**
     * Get the organization that owns the set.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the items of the set.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductSetItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * How many complete sets the combined stock of all locations can build.
     * Expects items.activeProducts to be loaded.
     */
    public function availableQuantity(): int
    {
        if ($this->items->isEmpty()) {
            return 0;
        }

        return (int) $this->items->map(
            fn (ProductSetItem $item) => (int) floor($item->activeProducts->sum('stock') / max($item->quantity, 1))
        )->min();
    }

    /**
     * Scope a query to only include sets of a specific organization.
     */
    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
