<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'slug', 'icon', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function products(): HasMany { return $this->hasMany(Product::class, 'category_id'); }
}
