<?php

namespace Orion\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Orion\Concerns\DisableRouteDiscovery;
use Orion\Contracts\QueryBuilder;
use Orion\Http\Controllers\Controller;
use Orion\Tests\Fixtures\App\Models\Post;
use Mockery;

class RouteDiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear existing routes
        Route::getRoutes()->refreshNameLookups();
    }

    /** @test */
    public function it_automatically_registers_routes_for_controllers()
    {
        // Create a test controller class
        $controllerClass = new class extends Controller {
            protected $model = Post::class;

            public static function getSlug(): string 
            {
                return 'test-controllers';
            }

            public function resolveResourceModelClass(): string
            {
                return $this->model;
            }

            public function getResourceQueryBuilder(): QueryBuilder
            {
                return Mockery::mock(QueryBuilder::class);
            }
        };

        // Register routes
        $controllerClass::registerRoutes();

        // Verify that routes are registered
        $this->assertTrue(Route::has('api.test-controllers.index'));
        $this->assertTrue(Route::has('api.test-controllers.store'));
        $this->assertTrue(Route::has('api.test-controllers.show'));
        $this->assertTrue(Route::has('api.test-controllers.update'));
        $this->assertTrue(Route::has('api.test-controllers.destroy'));
    }

    /** @test */
    public function it_respects_route_prefix_configuration()
    {
        // Set a custom route prefix in config
        config(['orion.route_discovery.route_prefix' => 'custom-prefix']);

        // Create a test controller class
        $controllerClass = new class extends Controller {
            protected $model = Post::class;

            public static function getSlug(): string 
            {
                return 'test-controllers';
            }

            public function resolveResourceModelClass(): string
            {
                return $this->model;
            }

            public function getResourceQueryBuilder(): QueryBuilder
            {
                return Mockery::mock(QueryBuilder::class);
            }
        };

        // Register routes
        $controllerClass::registerRoutes();

        // Verify that registered routes use this prefix
        $route = Route::getRoutes()->getByName('api.test-controllers.index');
        $this->assertStringStartsWith('custom-prefix/', $route->uri());
    }

    /** @test */
    public function it_applies_configured_middleware()
    {
        // Set custom middleware in config
        config(['orion.route_discovery.route_middleware' => ['test-middleware']]);

        // Create a test controller class
        $controllerClass = new class extends Controller {
            protected $model = Post::class;

            public static function getSlug(): string 
            {
                return 'test-controllers';
            }

            public function resolveResourceModelClass(): string
            {
                return $this->model;
            }

            public function getResourceQueryBuilder(): QueryBuilder
            {
                return Mockery::mock(QueryBuilder::class);
            }
        };

        // Register routes
        $controllerClass::registerRoutes();

        // Verify that registered routes use this middleware
        $route = Route::getRoutes()->getByName('api.test-controllers.index');
        $this->assertContains('test-middleware', $route->middleware());
    }

    /** @test */
    public function it_does_not_register_routes_for_controllers_with_disabled_discovery()
    {
        // Create a test controller with DisableRouteDiscovery
        $controllerClass = new class extends Controller {
            use DisableRouteDiscovery;

            protected $model = Post::class;

            public static function getSlug(): string 
            {
                return 'disabled-test-controllers';
            }

            public function resolveResourceModelClass(): string
            {
                return $this->model;
            }

            public function getResourceQueryBuilder(): QueryBuilder
            {
                return Mockery::mock(QueryBuilder::class);
            }
        };

        // Create an instance of the controller
        $instance = new $controllerClass();

        // Check if route discovery is enabled before registering routes
        if ($instance->routeDiscoveryEnabled()) {
            $controllerClass::registerRoutes();
        }

        // Verify that no routes are registered for it
        $this->assertFalse(Route::has('api.disabled-test-controllers.index'));
    }
}
