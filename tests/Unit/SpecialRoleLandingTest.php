<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleLandingService;
use App\Http\Middleware\EnforceDashboardDestination;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

function specialFixtureRole(string $name, bool $special, array $permissions, ?string $landing = null): Role
{
    $role = new Role(['name' => $name, 'is_special' => $special, 'landing_route' => $landing]);
    $role->setRelation('permissions', new Collection(array_map(fn ($permission) => new Permission(['name' => $permission]), $permissions)));
    return $role;
}

function specialFixtureRoutes(): array
{
    $page = new LaravelRoute(['GET'], 'assets', fn () => 'assets');
    $page->name('assets.index')->middleware(['permission:assets.view|view.assets']);
    $dashboard = new LaravelRoute(['GET'], 'dashboard', fn () => 'dashboard');
    $dashboard->name('dashboard')->middleware('permission:dashboard.view');
    $nested = new LaravelRoute(['GET'], 'limited', fn () => 'limited');
    $nested->name('limited.index')->middleware(['permission:assets.view', 'permission:reports.view']);
    $file = new LaravelRoute(['GET'], 'assets/export', fn () => 'file');
    $file->name('assets.export')->middleware('permission:assets.view');
    return [$page, $dashboard, $nested, $file];
}

beforeEach(function () {
    $routes = new \Illuminate\Routing\RouteCollection();
    foreach (specialFixtureRoutes() as $route) {
        $routes->add($route);
    }
    Route::shouldReceive('getRoutes')->andReturn($routes);
});

it('recognises any named flagged role and restricts mixed legacy assignments', function () {
    $user = new User;
    $user->setRelation('roles', new Collection([
        specialFixtureRole('Inventory Clerk', true, ['assets.view']),
        specialFixtureRole('Normal', false, ['dashboard.view']),
    ]));
    expect((new RoleLandingService)->isSpecialRole($user))->toBeTrue();
});

it('allows an OR permission alias but requires every independent middleware group', function () {
    $service = new RoleLandingService;
    expect($service->landingAllowedForPermissions('assets.index', ['view.assets']))->toBeTrue()
        ->and($service->landingAllowedForPermissions('limited.index', ['assets.view']))->toBeFalse()
        ->and($service->landingAllowedForPermissions('limited.index', ['assets.view', 'reports.view']))->toBeTrue()
        ->and($service->landingAllowedForPermissions('assets.index', ['assets.menu']))->toBeFalse();
});

it('never offers dashboard or download endpoints as landing pages', function () {
    expect(array_column((new RoleLandingService)->landingOptions(), 'route'))
        ->not->toContain('dashboard', 'assets.export');
});

it('falls back only to another assigned page when a stored landing becomes invalid', function () {
    $user = new User;
    $user->setRelation('roles', new Collection([specialFixtureRole('Clerk', true, ['assets.view'], 'dashboard')]));
    expect((new RoleLandingService)->destinationUrl($user))->toBe('/assets');
});

it('returns no destination for an empty special role and blocks its dashboard request', function () {
    $user = new User;
    $user->setRelation('roles', new Collection([specialFixtureRole('Clerk', true, [], 'dashboard')]));
    $service = new RoleLandingService;
    expect($service->destinationUrl($user))->toBeNull();
    $request = Request::create('/dashboard');
    $request->setUserResolver(fn () => $user);
    $request->setRouteResolver(fn () => specialFixtureRoutes()[1]);
    try {
        (new EnforceDashboardDestination($service))->handle($request, fn () => response('dashboard'));
        $this->fail('Dashboard must be blocked.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});

it('redirects direct dashboard requests to the permission-backed workspace', function () {
    $user = new User;
    $user->setRelation('roles', new Collection([specialFixtureRole('Clerk', true, ['assets.view'], 'assets.index')]));
    $request = Request::create('/dashboard');
    $request->setUserResolver(fn () => $user);
    $request->setRouteResolver(fn () => specialFixtureRoutes()[1]);
    $response = (new EnforceDashboardDestination(new RoleLandingService))->handle($request, fn () => response('dashboard'));
    expect($response->getStatusCode())->toBe(302)
        ->and(parse_url($response->headers->get('Location'), PHP_URL_PATH))->toBe('/assets');
});

it('preserves normal role dashboard responses', function () {
    $user = new User;
    $user->setRelation('roles', new Collection([specialFixtureRole('Normal', false, ['dashboard.view'])]));
    $request = Request::create('/dashboard');
    $request->setUserResolver(fn () => $user);
    $response = (new EnforceDashboardDestination(new RoleLandingService))->handle($request, fn () => response('normal dashboard'));
    expect($response->getContent())->toBe('normal dashboard');
});
