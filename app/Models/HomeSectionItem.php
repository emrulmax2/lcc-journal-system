<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HomeSectionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;

class HomeSectionItem extends Model
{
    /** @use HasFactory<HomeSectionItemFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sequence' => 'integer'];
    }

    /**
     * Lucide icon names an editor may choose from.
     *
     * An allow-list, not a free string: the frontend resolves the name against a static
     * import map, so an unknown name renders nothing at all — an invisible card with a
     * hole where the icon should be, and no error anywhere.
     *
     * @var list<string>
     */
    public const ICONS = [
        'FileSearch', 'Compass', 'Send', 'Database', 'BookOpen', 'Users',
        'ClipboardCheck', 'Gavel', 'Rocket', 'ShieldCheck', 'Globe', 'Sparkles',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::class, 'home_section_id');
    }

    public function url(): ?string
    {
        if (filled($this->route_name) && Route::has($this->route_name)) {
            return route($this->route_name);
        }

        return $this->external_url;
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'icon' => in_array($this->icon, self::ICONS, true) ? $this->icon : null,
            'title' => $this->title,
            'body' => $this->body,
            'meta' => $this->meta,
            'ctaLabel' => $this->cta_label,
            'url' => $this->url(),
        ];
    }
}
