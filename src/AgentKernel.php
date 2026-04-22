<?php

namespace MambaAi\Version_2;

use MambaAi\Version_2\Channel\ChannelResolverInterface;
use MambaAi\Version_2\Event\AgentEvent;
use MambaAi\Version_2\Event\ChannelEvent;
use MambaAi\Version_2\Event\MessageEvent;
use MambaAi\Version_2\Event\PromptEvent;
use MambaAi\Version_2\Event\RequestEvent;
use MambaAi\Version_2\Event\TerminateEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class AgentKernel
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ChannelResolverInterface $channelResolver,
        private AgentResolverInterface $agentResolver,
        private PromptBuilderInterface $promptBuilder,
        private StreamMapperInterface $streamMapper,
    ) {}

    public function handleCli(InputInterface $input): void
    {
        $this->handleCliMessage(
            $input->getOption('agent'),
            $input->getArgument('message'),
        );
    }

    public function handleCliMessage(string $agent, string $message, ?OutputInterface $output = null): void
    {
        $request = new Request();
        $request->attributes->set('_channel', 'cli');
        $request->attributes->set('_agent', $agent);
        $request->attributes->set('_content', $message);
        $request->attributes->set('_output', $output);

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

        $renderer = $channel->getRenderer();
        $answers = [];

        try {
            $result = $agent->call($prompt);
        } catch (\Throwable $e) {
            $errorMessage = new Message(agent: $agent->name, content: $e->getMessage(), type: MessageType::Error);
            $rendered = $renderer->render($errorMessage);
            if ($rendered !== null) {
                $channel->send($rendered);
            }
            $channel->finalize();
            $this->eventDispatcher->dispatch(new TerminateEvent([$errorMessage], $agent, $message));
            return;
        }

        foreach ($this->streamMapper->map($agent->name, $result) as $message) {
            $rendered = $renderer->render($message);
            if ($rendered !== null) {
                $channel->send($rendered);
            }
            $answers[] = $message;
        }

        $channel->finalize();

        $this->eventDispatcher->dispatch(new TerminateEvent($answers, $agent, $message));
    }
}
