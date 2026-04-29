<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit;

use MambaAi\Agent;
use MambaAi\Event\BuildOptionPrompt;
use MambaAi\Event\BuildSystemPrompt;
use MambaAi\Event\BuildUserPrompt;
use MambaAi\Message;
use MambaAi\Prompt\SystemPromptPartInterface;
use MambaAi\Prompt\UserPromptPartInterface;
use MambaAi\PromptBuilder;
use MambaAi\Tests\Support\Doubles\FakeEventDispatcher;
use MambaAi\Tests\Support\Factory\AgentFactory;
use MambaAi\Tests\Support\Factory\MessageFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;

final class PromptBuilderTest extends TestCase
{
    #[Test]
    public function it_falls_back_to_helpful_assistant_when_no_system_parts(): void
    {
        $builder = new PromptBuilder(
            new FakeEventDispatcher(),
            systemParts: [],
            userParts: [new MessageContentUserPart()],
        );

        $prompt = $builder->build(AgentFactory::make(), MessageFactory::make());

        self::assertSame('You are a helpful assistant.', $this->systemContent($prompt->SystemMessages));
    }

    #[Test]
    public function it_throws_logic_exception_when_user_parts_produce_no_blocks(): void
    {
        $builder = new PromptBuilder(
            new FakeEventDispatcher(),
            systemParts: [],
            userParts: [],
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('agent "default"');

        $builder->build(AgentFactory::make(name: 'default'), MessageFactory::make());
    }

    #[Test]
    public function it_excludes_parts_by_short_class_name(): void
    {
        $alpha = new AlphaSystemPart();
        $beta = new BetaSystemPart();

        $builder = new PromptBuilder(
            new FakeEventDispatcher(),
            systemParts: [$alpha, $beta],
            userParts: [new MessageContentUserPart()],
        );

        $prompt = $builder->build(
            AgentFactory::make(excludedParts: ['AlphaSystemPart']),
            MessageFactory::make(),
        );

        $content = $this->systemContent($prompt->SystemMessages);
        self::assertStringNotContainsString('alpha-content', $content);
        self::assertStringContainsString('beta-content', $content);
    }

    #[Test]
    public function it_skips_parts_whose_target_agent_doesnt_match(): void
    {
        $forOther = new TargetedSystemPart('other-agent', 'other-content');
        $forAll = new TargetedSystemPart(null, 'shared-content');

        $builder = new PromptBuilder(
            new FakeEventDispatcher(),
            systemParts: [$forOther, $forAll],
            userParts: [new MessageContentUserPart()],
        );

        $prompt = $builder->build(AgentFactory::make(name: 'mambi'), MessageFactory::make());

        $content = $this->systemContent($prompt->SystemMessages);
        self::assertStringNotContainsString('other-content', $content);
        self::assertStringContainsString('shared-content', $content);
    }

    #[Test]
    public function it_lets_listeners_mutate_messages_and_options(): void
    {
        $dispatcher = new FakeEventDispatcher();

        $dispatcher->on(BuildSystemPrompt::class, static function (BuildSystemPrompt $event): void {
            $event->messages = new MessageBag(new SystemMessage('mutated-system'));
        });
        $dispatcher->on(BuildUserPrompt::class, static function (BuildUserPrompt $event): void {
            $event->messages = new MessageBag(new UserMessage(new Text('mutated-user')));
        });
        $dispatcher->on(BuildOptionPrompt::class, static function (BuildOptionPrompt $event): void {
            $event->options = ['stream' => false, 'temperature' => 0.5];
        });

        $builder = new PromptBuilder(
            $dispatcher,
            systemParts: [new AlphaSystemPart()],
            userParts: [new MessageContentUserPart()],
        );

        $prompt = $builder->build(AgentFactory::make(), MessageFactory::make());

        self::assertSame('mutated-system', $this->systemContent($prompt->SystemMessages));
        self::assertSame(['stream' => false, 'temperature' => 0.5], $prompt->options);
        self::assertCount(3, $dispatcher->dispatched);
        self::assertInstanceOf(BuildSystemPrompt::class, $dispatcher->dispatched[0]);
        self::assertInstanceOf(BuildUserPrompt::class, $dispatcher->dispatched[1]);
        self::assertInstanceOf(BuildOptionPrompt::class, $dispatcher->dispatched[2]);
    }

    private function systemContent(MessageBag $bag): string
    {
        foreach ($bag as $msg) {
            if ($msg instanceof SystemMessage) {
                return (string) $msg->getContent();
            }
        }
        self::fail('No SystemMessage found in MessageBag.');
    }
}

final class AlphaSystemPart implements SystemPromptPartInterface
{
    public function getTargetAgent(): ?string
    {
        return null;
    }

    public function getContent(Agent $agent, Message $message): ?string
    {
        return 'alpha-content';
    }
}

final class BetaSystemPart implements SystemPromptPartInterface
{
    public function getTargetAgent(): ?string
    {
        return null;
    }

    public function getContent(Agent $agent, Message $message): ?string
    {
        return 'beta-content';
    }
}

final class TargetedSystemPart implements SystemPromptPartInterface
{
    public function __construct(private ?string $target, private string $content)
    {
    }

    public function getTargetAgent(): ?string
    {
        return $this->target;
    }

    public function getContent(Agent $agent, Message $message): ?string
    {
        return $this->content;
    }
}

final class MessageContentUserPart implements UserPromptPartInterface
{
    public function getTargetAgent(): ?string
    {
        return null;
    }

    public function getBlocks(Agent $agent, Message $message): array
    {
        return [new Text($message->content)];
    }
}
