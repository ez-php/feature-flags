<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\FeatureFlags\Driver\ArrayDriver;
use EzPhp\FeatureFlags\FeatureMiddleware;
use EzPhp\FeatureFlags\FlagManager;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use LogicException;

/**
 * @covers \EzPhp\FeatureFlags\FeatureMiddleware
 */
final class FeatureMiddlewareTest extends TestCase
{
    /**
     * @param array<string, bool> $flags
     * @param list<string>        $parameters
     */
    private function respond(array $flags, array $parameters): ResponseInterface
    {
        $middleware = new FeatureMiddleware(new FlagManager(new ArrayDriver($flags)));

        return $middleware->handle(
            new Request('GET', '/beta'),
            static fn (): Response => new Response('reached', 200),
            ...$parameters,
        );
    }

    public function testAnEnabledFlagPassesThrough(): void
    {
        self::assertSame(200, $this->respond(['beta' => true], ['beta'])->status());
    }

    public function testADisabledFlagAnswers404(): void
    {
        self::assertSame(404, $this->respond(['beta' => false], ['beta'])->status());
    }

    public function testAnUnknownFlagAnswers404(): void
    {
        self::assertSame(404, $this->respond([], ['beta'])->status());
    }

    public function testEveryListedFlagMustBeEnabled(): void
    {
        self::assertSame(404, $this->respond(['a' => true, 'b' => false], ['a', 'b'])->status());
        self::assertSame(200, $this->respond(['a' => true, 'b' => true], ['a', 'b'])->status());
    }

    public function testNoFlagNameIsAConfigurationError(): void
    {
        $this->expectException(LogicException::class);

        $this->respond(['beta' => true], []);
    }
}
