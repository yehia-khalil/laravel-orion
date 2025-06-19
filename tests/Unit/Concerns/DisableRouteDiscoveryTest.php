<?php

namespace Orion\Tests\Unit\Concerns;

use Mockery;
use Orion\Concerns\DisableRouteDiscovery;
use Orion\Contracts\QueryBuilder;
use Orion\Http\Controllers\Controller;
use Orion\Tests\Fixtures\App\Models\Post;
use Orion\Tests\Unit\TestCase;

class DisableRouteDiscoveryTest extends TestCase
{
    /** @test */
    public function it_disables_route_discovery()
    {
        $controller = new class extends Controller {
            use DisableRouteDiscovery;

            protected $model = Post::class;

            public function resolveResourceModelClass(): string
            {
                return Post::class;
            }

            public function getResourceQueryBuilder(): QueryBuilder
            {
                return Mockery::mock(QueryBuilder::class);
            }
        };

        $this->assertFalse($controller->routeDiscoveryEnabled());
    }
}
