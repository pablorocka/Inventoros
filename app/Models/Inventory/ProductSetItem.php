<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductSetItem extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_set_id',
        'name',
        'quantity',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the set that owns the item.
     */
    public function set(): BelongsTo
    {
        return $this->belongsTo(ProductSet::class, 'product_set_id');
    }

    /**
     * Get the linked product instances (one per location).
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_set_item_product');
    }

    /**
     * Get the linked product instances that can still be sold.
     */
    public function activeProducts(): BelongsToMany
    {
        return $this->products()->where('products.is_active', true);
    }
}
