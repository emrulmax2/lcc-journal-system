<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * The routes a menu item or a homepage card may point at — an ALLOW-LIST, built from the
 * router itself.
 *
 * Not a free text box, and not a hand-maintained array either.
 *
 *   - A free text box lets an editor type `articles.index ` (trailing space) or a route
 *     that was renamed last month. MenuItem::saving() would throw, which is right, but the
 *     editor would be guessing at what the valid names are.
 *   - A hand-maintained array goes stale the moment someone adds a route, and nothing in
 *     the system would ever notice.
 *
 * So it is derived: every GET route that is NAMED, is not in /admin, and takes NO
 * PARAMETERS. A parameterised route (`articles.show`, `pages.show`) cannot be linked from a
 * menu without also choosing the parameter, and `route()` would throw on the missing one —
 * a page that 500s the moment an editor saves. Pages are linked by page_id instead, which
 * is what page_id is for.
 */
final class PublicRoutes
{
    /**
     * Named, parameterless GET routes that are nonetheless not somewhere to send a reader.
     *
     * `dashboard` is a person's own editorial office, `login` is a means and not a
     * destination, and `sitemap` is for machines.
     *
     * @var list<string>
     */
    private const DENY = ['login', 'logout', 'dashboard', 'sitemap'];

    /**
     * name => path, sorted by name. The path is shown in the admin so an editor can see
     * where the link actually goes before they save it.
     *
     * @return array<string, string>
     */
    public static function linkable(): array
    {
        $linkable = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || in_array($name, self::DENY, true)) {
                continue;
            }

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (str_starts_with($name, 'admin.')) {
                continue;
            }

            // route() would throw without the parameter, and a menu cannot supply one.
            if ($route->parameterNames() !== []) {
                continue;
            }

            $linkable[$name] = '/'.ltrim($route->uri(), '/');
        }

        ksort($linkable);

        return $linkable;
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::linkable());
    }

    /** @return list<array{name: string, path: string}> The shape the admin screens render. */
    public static function options(): array
    {
        return collect(self::linkable())
            ->map(fn (string $path, string $name): array => ['name' => $name, 'path' => $path])
            ->values()
            ->all();
    }
}
