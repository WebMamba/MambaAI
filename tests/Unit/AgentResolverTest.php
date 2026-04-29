<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit;

use MambaAi\AgentResolver;
use MambaAi\Tests\Support\Doubles\FakeAgentLoader;
use MambaAi\Tests\Support\Factory\AgentFactory;
use MambaAi\Tests\Support\Factory\MessageFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AgentResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_by_name(): void
    {
        $mambi = AgentFactory::make(name: 'mambi');
        $other = AgentFactory::make(name: 'other');
        $resolver = new AgentResolver(new FakeAgentLoader([$mambi, $other]));

        $resolved = $resolver->resolve(MessageFactory::make(agent: 'mambi'));

        self::assertSame($mambi, $resolved);
    }

    #[Test]
    public function it_falls_back_to_default_when_name_missing(): void
    {
        $default = AgentFactory::make(name: 'default');
        $resolver = new AgentResolver(new FakeAgentLoader([$default]));

        $resolved = $resolver->resolve(MessageFactory::make(agent: 'unknown'));

        self::assertSame($default, $resolved);
    }

    #[Test]
    public function it_throws_when_neither_name_nor_default_exists(): void
    {
        $resolver = new AgentResolver(new FakeAgentLoader([AgentFactory::make(name: 'foo')]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No agent "bar" and no "default"');

        $resolver->resolve(MessageFactory::make(agent: 'bar'));
    }

    #[Test]
    public function it_loads_agents_only_once(): void
    {
        $loader = new FakeAgentLoader([AgentFactory::make(name: 'default')]);
        $resolver = new AgentResolver($loader);

        $resolver->resolve(MessageFactory::make(agent: 'default'));
        $resolver->resolve(MessageFactory::make(agent: 'default'));
        $resolver->resolve(MessageFactory::make(agent: 'unknown'));

        self::assertSame(1, $loader->loadCallCount);
    }
}
