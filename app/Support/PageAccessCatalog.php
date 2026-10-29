<?php

namespace App\Support;

use Illuminate\Routing\Route;

class PageAccessCatalog
{
    /** Keep all non-permission middleware, and preserve legacy permission paths. */
    public function middlewareFor(Route $route): array
    {
        $middleware = (array) ($route->getAction('middleware') ?? []);
        // Config keys contain dots: read the keyed array instead of dot lookup.
        $catalog = config('page_access', []);
        $entry = $catalog[$route->getName()] ?? null;
        if (!$entry || !in_array('GET', $route->methods(), true) || $route->parameterNames() !== []) return (array) $middleware;
        $aliases = $entry['permissions'];
        $found = false;
        foreach ($middleware as &$item) {
            if (!is_string($item) || !str_starts_with($item, 'permission:')) continue;
            $found = true;
            $parts = explode(',', substr($item, 11), 2);
            $names = array_unique(array_merge(explode('|', $parts[0]), $aliases));
            $item = 'permission:'.implode('|', $names).(isset($parts[1]) ? ','.$parts[1] : '');
        }
        unset($item);
        if (!$found) $middleware[] = 'permission:'.implode('|', $aliases);
        return $middleware;
    }

    public function apply(Route $route): void
    {
        $action = $route->getAction();
        $action['middleware'] = $this->middlewareFor($route);
        $route->setAction($action);
        // Laravel caches gathered middleware after its first use.
        $route->computedMiddleware = null;
    }
}
