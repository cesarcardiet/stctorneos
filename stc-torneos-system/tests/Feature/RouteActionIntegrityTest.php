<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Tests\TestCase;

class RouteActionIntegrityTest extends TestCase
{
    public function test_every_controller_route_points_to_an_existing_method(): void
    {
        $missing = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn (Route $route) => [
                'uri' => $route->uri(),
                'action' => $route->getActionName(),
            ])
            ->filter(fn (array $route) => str_contains($route['action'], '@'))
            ->reject(function (array $route) {
                [$controller, $method] = explode('@', $route['action'], 2);

                return class_exists($controller) && method_exists($controller, $method);
            })
            ->map(fn (array $route) => $route['uri'].' -> '.$route['action'])
            ->values()
            ->all();

        $this->assertSame([], $missing, "Hay rutas apuntando a métodos inexistentes:\n".implode("\n", $missing));
    }
}
