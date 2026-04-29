<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit\Channel;

use MambaAi\Channel\ChannelResolver;
use MambaAi\Tests\Support\Doubles\FakeChannel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ChannelResolverTest extends TestCase
{
    #[Test]
    public function it_returns_first_supporting_channel(): void
    {
        $first = new FakeChannel(supports: false);
        $second = new FakeChannel(supports: true);
        $third = new FakeChannel(supports: true);
        $resolver = new ChannelResolver([$first, $second, $third]);

        self::assertSame($second, $resolver->resolve(new Request()));
    }

    #[Test]
    public function it_throws_when_no_channel_supports(): void
    {
        $resolver = new ChannelResolver([new FakeChannel(supports: false)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No channel supports');

        $resolver->resolve(new Request());
    }

    #[Test]
    public function it_short_circuits_after_first_match(): void
    {
        $first = new FakeChannel(supports: true);
        $second = new FakeChannel(supports: true);
        $resolver = new ChannelResolver([$first, $second]);

        $resolver->resolve(new Request());

        self::assertSame(1, $first->supportsCallCount);
        self::assertSame(0, $second->supportsCallCount);
    }
}
