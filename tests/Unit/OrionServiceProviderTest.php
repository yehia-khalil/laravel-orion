<?php

namespace Orion\Tests\Unit;

use Illuminate\Support\Facades\File;
use Mockery;
use Orion\Http\Controllers\Controller;
use Orion\OrionServiceProvider;
use Orion\Tests\Fixtures\App\Models\Post;

class OrionServiceProviderTest extends TestCase
{
    /** @test */
    public function it_discovers_controllers_when_enabled_in_config()
    {
        $provider = Mockery::mock(OrionServiceProvider::class, [$this->app])->makePartial();
        $provider->shouldAllowMockingProtectedMethods();
        $provider->shouldReceive('discoverAndRegisterControllers')->once();

        config(['orion.route_discovery.enabled' => true]);

        $provider->boot();
    }

    /** @test */
    public function it_does_not_discover_controllers_when_disabled_in_config()
    {
        $provider = Mockery::mock(OrionServiceProvider::class, [$this->app])->makePartial();
        $provider->shouldAllowMockingProtectedMethods();
        $provider->shouldReceive('discoverAndRegisterControllers')->never();

        config(['orion.route_discovery.enabled' => false]);

        $provider->boot();
    }

    /** @test */
    public function it_converts_path_to_namespace_correctly()
    {
        $provider = new TestableOrionServiceProvider($this->app);

        $path = base_path('app/Http/Controllers/Api');
        $namespace = $provider->testPathToNamespace($path);

        $this->assertEquals('App\\Http\\Controllers\\Api', $namespace);
    }

    /** @test */
    public function it_registers_discovered_controllers()
    {
        $provider = new TestableOrionServiceProvider($this->app);

        // Create a mock path that exists
        $mockPath = sys_get_temp_dir() . '/orion-test';
        if (!is_dir($mockPath)) {
            mkdir($mockPath);
        }

        // Mock File facade
        $mockFile = Mockery::mock(\Symfony\Component\Finder\SplFileInfo::class);
        $mockFile->shouldReceive('getRelativePathname')->andReturn('TestController.php');

        File::shouldReceive('allFiles')->with($mockPath)->once()->andReturn([
            $mockFile
        ]);

        // Mock controller class
        $controllerClass = 'App\\Http\\Controllers\\Api\\TestController';
        $controllerMock = Mockery::mock('overload:'.$controllerClass);
        $controllerMock->shouldReceive('routeDiscoveryEnabled')->once()->andReturn(true);
        $controllerMock->shouldReceive('registerRoutes')->once();

        config(['orion.route_discovery.paths' => [$mockPath]]);

        $provider->testDiscoverAndRegisterControllers();

        // Clean up
        if (is_dir($mockPath)) {
            rmdir($mockPath);
        }
    }
}

class TestableOrionServiceProvider extends OrionServiceProvider
{
    public function testPathToNamespace(string $path): string
    {
        return $this->pathToNamespace($path);
    }

    public function testDiscoverAndRegisterControllers(): void
    {
        $this->discoverAndRegisterControllers();
    }
}
