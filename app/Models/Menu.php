<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Content\SiteContent;
use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sequence' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => SiteContent::flush());
        static::deleted(fn () => SiteContent::flush());
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sequence');
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }
}
