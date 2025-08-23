<?php

declare(strict_types=1);

namespace Orion\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Orion\Exceptions\RouteDiscoveryException;
use Orion\Facades\Orion;
use Orion\Http\Controllers\RelationController;

trait HandlesRouteDiscovery
{
    protected static $slug = null;
    protected static $routePrefix = null;
    protected static $routeNamePrefix = null;
    protected static $routeMiddleware = [];
    protected static $withoutRouteMiddleware = [];

    /**
     * Determine whether route auto discovery is enabled.
     *
     * @return bool
     */
    public function routeDiscoveryEnabled(): bool
    {
        return !property_exists($this, 'routeDiscoveryDisabled');
    }

    public static function registerRoutes(): void
    {
        if (static::isRelationController()) {
            static::registerRelationRoutes();
        } else {
            static::registerResourceRoutes();
        }
    }

    protected static function registerResourceRoutes(): void
    {
        $slug = static::getSlug();

        Route::middleware(static::getRouteMiddleware())
            ->withoutMiddleware(static::getWithoutRouteMiddleware())
            ->prefix(static::getRoutePrefix())
            ->name(static::getRouteNamePrefix() . '.')
            ->group(function () use ($slug) {
                $controller = static::class;
                $route = Orion::resource($slug, $controller);

                if (static::usesSoftDeletes($controller)) {
                    $route->withSoftDeletes();
                }
            });
    }

    protected static function registerRelationRoutes(): void
    {
        $controller = static::class;
        $instance = app($controller);

        $model = $instance->model ?? null;
        $relation = isset($instance->relation) ? $instance->relation : null;
        $type = isset($instance->resourceType) ? $instance->resourceType : static::detectRelationType($model, $relation);

        if (! $model || ! $relation || ! $type) {
            throw new RouteDiscoveryException("Cannot register relation route: model [$model], relation [$relation], type [$type]");
        }

        $parentSlug = str(class_basename($model))->kebab()->plural();

        Route::middleware(static::getRouteMiddleware())
            ->withoutMiddleware(static::getWithoutRouteMiddleware())
            ->prefix(static::getRoutePrefix())
            ->name(static::getRouteNamePrefix() . '.')
            ->group(function () use ($type, $parentSlug, $relation, $controller, $instance) {

                switch ($type) {
                    case 'hasOne':
                        $route = Orion::hasOneResource($parentSlug, $relation, $controller);
                        break;
                    case 'hasMany':
                        $route = Orion::hasManyResource($parentSlug, $relation, $controller);
                        break;
                    case 'belongsTo':
                        $route = Orion::belongsToResource($parentSlug, $relation, $controller);
                        break;
                    case 'belongsToMany':
                        $route = Orion::belongsToManyResource($parentSlug, $relation, $controller);
                        break;
                    case 'hasOneThrough':
                        $route = Orion::hasOneThroughResource($parentSlug, $relation, $controller);
                        break;
                    case 'hasManyThrough':
                        $route = Orion::hasManyThroughResource($parentSlug, $relation, $controller);
                        break;
                    case 'morphOne':
                        $route = Orion::morphOneResource($parentSlug, $relation, $controller);
                        break;
                    case 'morphMany':
                        $route = Orion::morphManyResource($parentSlug, $relation, $controller);
                        break;
                    case 'morphTo':
                        $route = Orion::morphToResource($parentSlug, $relation, $controller);
                        break;
                    case 'morphToMany':
                        $route = Orion::morphToManyResource($parentSlug, $relation, $controller);
                        break;
                    case 'morphedByMany':
                        $route = Orion::morphedByManyResource($parentSlug, $relation, $controller);
                        break;
                    default:
                        throw new RouteDiscoveryException("Unsupported relation type [$type] on [$parentSlug -> $relation]");
                }

                if (static::usesRelatedSoftDeletes($instance)) {
                    $route->withSoftDeletes();
                }
            });
    }

    protected static function isRelationController(): bool
    {
        return is_subclass_of(static::class, RelationController::class);
    }

    protected static function detectRelationType($model, $relation): ?string
    {
        if (! method_exists($model, $relation)) {
            return null;
        }

        $instance = new $model;
        $relationInstance = $instance->{$relation}();

        $map = [
            HasOne::class => 'hasOne',
            HasOneThrough::class => 'hasOneThrough',
            MorphOne::class => 'morphOne',
            BelongsTo::class => 'belongsTo',
            MorphTo::class => 'morphTo',
            HasMany::class => 'hasMany',
            HasManyThrough::class => 'hasManyThrough',
            MorphMany::class => 'morphMany',
            BelongsToMany::class => 'belongsToMany',
            MorphToMany::class => static::isMorphedByMany($model, $relation) ? 'morphedByMany' : 'morphToMany',
        ];

        foreach ($map as $class => $type) {
            if ($relationInstance instanceof $class) {
                return $type;
            }
        }

        return null;
    }

    protected static function isMorphedByMany($model, $relation): bool
    {
        $instance = new $model;

        if (! method_exists($instance, $relation)) {
            return false;
        }

        $relationInstance = $instance->{$relation}();

        return $relationInstance instanceof MorphToMany && $relationInstance->getInverse();
    }

    protected static function usesSoftDeletes($controller): bool
    {
        $instance = app($controller);

        if (! method_exists($instance, 'resolveResourceModelClass')) {
            return false;
        }

        $modelClass = $instance->resolveResourceModelClass();

        return class_exists($modelClass)
            && in_array(SoftDeletes::class, class_uses_recursive($modelClass));
    }

    protected static function usesRelatedSoftDeletes($controller): bool
    {
        $model = $controller->model ?? null;
        $relation = $controller->relation ?? null;

        if (! $model || ! method_exists($model, $relation)) {
            return false;
        }

        $related = $model::query()->getModel()->{$relation}()->getRelated();

        return in_array(SoftDeletes::class, class_uses_recursive($related));
    }

    public static function getSlug(): string
    {
        if (! empty(static::$slug)) {
            return static::$slug;
        }

        return (string) str(class_basename(static::class))
            ->beforeLast('Controller')
            ->kebab()
            ->plural();
    }

    public static function getRoutePrefix(): string
    {
        return static::$routePrefix ?: config('orion.route_discovery.route_prefix', 'api');
    }

    public static function getRouteNamePrefix(): string
    {
        return static::$routeNamePrefix ?: config('orion.route_discovery.route_name_prefix', 'api');
    }

    public static function getRouteName(): string
    {
        return static::getRouteNamePrefix() . '.' . static::getRelativeRouteName();
    }

    public static function getRoutePath(): string
    {
        return '/' . static::getSlug();
    }

    public static function getRelativeRouteName(): string
    {
        return (string) str(static::getSlug())->replace('/', '.');
    }

    public static function getRouteMiddleware()
    {
        return array_merge(
            config('orion.route_discovery.route_middleware', []),
            Arr::wrap(static::$routeMiddleware)
        );
    }

    public static function getWithoutRouteMiddleware(): array
    {
        return Arr::wrap(static::$withoutRouteMiddleware);
    }
}