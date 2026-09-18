<?php
namespace BBS\App\Models;

use BBS\Core\Model;

class Service extends Model
{
    protected static string $table = 'services';
    protected static array $fillable = [
        'tenant_id', 'uuid', 'name', 'slug', 'description', 'category_id',
        'duration_minutes', 'price', 'deposit_type', 'deposit_amount',
        'deposit_percentage', 'currency', 'color', 'icon', 'image',
        'max_per_day', 'requires_review', 'allows_image_upload',
        'is_active', 'sort_order',
    ];
}
