<?php

declare(strict_types=1);

namespace MambaAi\Message;

use MambaAi\AgentKernel;
use MambaAi\Channel\ChannelResolverInterface;
use MambaAi\Channel\SlackChannel;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MambaAIMessageHandler
{
    public function __construct(
        private AgentKernel $agentKernel,
        private ChannelResolverInterface $channelResolver,
    ) {
    }

    public function __invoke(MambaAIMessage $message): void
    {
        $request = $message->toRequest();
        $messages = $this->agentKernel->handle($request);
        $channel = $this->channelResolver->resolve($request);

        if ($channel instanceof SlackChannel) {
            $channel->post($messages);

            return;
        }

        // Drain the iterable so TerminateEvent fires.
        foreach ($messages as $_) {
        }
    }
}
