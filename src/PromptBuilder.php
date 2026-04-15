<?php

namespace MambaAi\Version_2;

use MambaAi\Version_2\Event\BuildOptionPrompt;
use MambaAi\Version_2\Event\BuildSystemPrompt;
use MambaAi\Version_2\Event\BuildUserPrompt;
use MambaAi\Version_2\Prompt\SystemPromptPartInterface;
use MambaAi\Version_2\Prompt\UserPromptPartInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;

class PromptBuilder implements PromptBuilderInterface
{
    /**
     * @param iterable<SystemPromptPartInterface> $systemParts
     * @param iterable<UserPromptPartInterface>   $userParts
     */
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private iterable $systemParts,
        private iterable $userParts,
    ) {}

    public function build(Agent $agent, Message $message): Prompt
    {

        $systemMessages = $this->buildSystemPrompt($agent, $message);
        $event = $this->dispatcher->dispatch(new BuildSystemPrompt($agent, $message, $systemMessages));
        $systemMessages = $event->messages;

        $userMessages = $this->buildUserPrompt($agent, $message);
        $event = $this->dispatcher->dispatch(new BuildUserPrompt($agent, $message, $userMessages));
        $userMessages = $event->messages;

        // --- Options assembly ---
        $options = ['stream' => $agent->stream];
        $event = $this->dispatcher->dispatch(new BuildOptionPrompt($agent, $message, $options));
        $options = $event->options;

        return new Prompt($userMessages, $systemMessages, $options);
    }

    private function buildSystemPrompt(Agent $agent, Message $message): MessageBag
    {
        $systemContributions = [];
        foreach ($this->systemParts as $part) {
            if (!$this->partApplies($part, $agent)) {
                continue;
            }
            $content = $part->getContent($agent, $message);
            if ($content !== null && $content !== '') {
                $systemContributions[] = $content;
            }
        }

        return new MessageBag(
            new SystemMessage(
                $systemContributions !== []
                    ? implode("\n\n", $systemContributions)
                    : 'You are a helpful assistant.'
            ),
        );
    }

    private function buildUserPrompt(Agent $agent, Message $message): MessageBag
    {
        // --- User message assembly ---
        $userBlocks = [];
        foreach ($this->userParts as $part) {
            if (!$this->partApplies($part, $agent)) {
                continue;
            }
            foreach ($part->getBlocks($agent, $message) as $block) {
                $userBlocks[] = $block;
            }
        }

        if ($userBlocks === []) {
            throw new \LogicException(
                sprintf(
                    'No user prompt parts produced any content for agent "%s". '.
                    'Register at least one UserPromptPartInterface service.',
                    $agent->name
                )
            );
        }

        return new MessageBag(new UserMessage(...$userBlocks));
    }

    private function partApplies(SystemPromptPartInterface|UserPromptPartInterface $part, Agent $agent): bool
    {
        $target = $part->getTargetAgent();
        if ($target !== null && $target !== $agent->name) {
            return false;
        }

        $shortName = substr(strrchr($part::class, '\\'), 1);

        return !in_array($shortName, $agent->excludedParts, true);
    }
}
