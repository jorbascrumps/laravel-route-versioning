<?php

namespace jorbascrumps\SemanticRouteVersion;

use Composer\Semver\Semver;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class ServiceProvider extends RouteServiceProvider {
    public function boot(): void
    {
        $this->routes(function () {
            $this->registerRoutes();

            Route::fallback(function() {
                $method = request()->method();
                $versionConstraint = request()->header('Version');

                $routeFiles = $this->getRouteFiles();
                $registeredVersions = collect($routeFiles)
                    ->map(fn ($route) => pathinfo($route))
                    ->pluck('filename')
                    ->reverse()
                    ->toArray();
                $matchedVersions = Semver::satisfiedBy($registeredVersions, $versionConstraint);

                foreach ($matchedVersions as $version) {
                    $next = Request::create($version, $method, []);
                    $match = Route::getRoutes()->match($next);

                    if ($match->isFallback) {
                        continue;
                    }

                    return app()->handle($next)->getContent();
                }
            });
        });
    }

    private function getRouteFiles(): array
    {
        return glob(base_path('routes/api/*.php'));
    }

    private function registerRoutes(): void
    {
        $routesFiles = $this->getRouteFiles();

        foreach ($routesFiles as $file) {
            $path = pathinfo($file);

            Route::prefix($path['filename'])
                ->namespace($this->namespace)
                ->group($file);
        }
    }
}
