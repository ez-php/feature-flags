<?php

declare(strict_types=1);

namespace EzPhp\FeatureFlags;

use EzPhp\Contracts\ParameterizedMiddlewareInterface;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use LogicException;

/**
 * Class FeatureMiddleware
 *
 * Route middleware that hides a route behind feature flags: every flag named in
 * the registration must be enabled, otherwise the response is 404, so a disabled
 * feature looks like it does not exist. Register it by name with parameters:
 *
 *   $app->middlewareAlias('feature', FeatureMiddleware::class);
 *   $router->get('/beta', $handler)->middleware('feature:new-checkout');
 *
 * Global flag state only — per-context rollouts (Flag::enabledFor()) need the
 * request's user, so they stay in the application.
 *
 * @package EzPhp\FeatureFlags
 */
final readonly class FeatureMiddleware implements ParameterizedMiddlewareInterface
{
    /**
     * @param FlagManager $flags
     */
    public function __construct(private FlagManager $flags)
    {
    }

    /**
     * @param RequestInterface                              $request
     * @param callable(RequestInterface): ResponseInterface $next
     * @param string                                        ...$parameters Flag names from `feature:a,b`.
     *
     * @return ResponseInterface
     *
     * @throws LogicException When no flag is named.
     */
    public function handle(RequestInterface $request, callable $next, string ...$parameters): ResponseInterface
    {
        if ($parameters === []) {
            throw new LogicException("FeatureMiddleware has no flag: register it as 'feature:name'.");
        }

        foreach ($parameters as $flag) {
            if (!$this->flags->enabled($flag)) {
                return (new Response('Not Found', 404))
                    ->withHeader('Content-Type', 'text/plain; charset=UTF-8');
            }
        }

        return $next($request);
    }
}
