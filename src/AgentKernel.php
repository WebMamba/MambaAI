<?php

namespace MambaAi\Version_2;

use MambaAi\Version_2\Channel\ChannelResolverInterface;
use MambaAi\Version_2\Event\AgentEvent;
use MambaAi\Version_2\Event\MessageEvent;
use MambaAi\Version_2\Event\PromptEvent;
use MambaAi\Version_2\Event\RequestEvent;
use MambaAi\Version_2\Event\TerminateEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\HttpFoundation\Request;
use MambaAi\Version_2\Event\ChannelEvent;

class AgentKernel
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ChannelResolverInterface $channelResolver,
        private AgentResolverInterface $agentResolver,
        private PromptBuilderInterface $promptBuilder,
    ) {}

    public function handleCli(InputInterface $input): void
    {
        $request = new Request();
        $request->attributes->set('_channel', 'cli');
        $request->attributes->set('_agent', $input->getOption('agent'));
        $request->attributes->set('_content', $input->getArgument('message'));

        $this->handle($request);
    }

    public function handle(Request $request): void
    {
        $event = $this->eventDispatcher->dispatch(new RequestEvent($request));
        $request = $event->request;

        $channel = $this->channelResolver->resolve($request);
        /** @var ChannelEvent $event */
        $event = $this->eventDispatcher->dispatch(new ChannelEvent($channel));
        $channel = $event->channel;

        $message = $channel->receive($request);
        // sur cet event on peut par exemple mettre le système de la mémoire
        /** @var MessageEvent $event */
        $event = $this->eventDispatcher->dispatch(new MessageEvent($message));
        $message = $event->message;

        $agent = $this->agentResolver->resolve($message);
        $event = $this->eventDispatcher->dispatch(new AgentEvent($agent, $message));
        $agent = $event->agent;

        $prompt = $this->promptBuilder->build($agent, $message);
        $event = $this->eventDispatcher->dispatch(new PromptEvent($prompt, $agent, $message));
        $prompt = $event->prompt;

        $answers = [];
        foreach($agent->call($prompt) as $answer) {
            $channel->send($answer);
            $answers[] = $answer;
        }

        $channel->finalize();

        $this->eventDispatcher->dispatch(new TerminateEvent($answers, $agent, $message));
    }
}
