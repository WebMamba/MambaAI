<?php

declare(strict_types=1);

namespace MambaAi;

use MambaAi\Channel\ChannelResolverInterface;
use MambaAi\Event\AgentEvent;
use MambaAi\Event\ChannelEvent;
use MambaAi\Event\MessageEvent;
use MambaAi\Event\PromptEvent;
use MambaAi\Event\RequestEvent;
use MambaAi\Event\TerminateEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AgentKernel
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ChannelResolverInterface $channelResolver,
        private AgentResolverInterface $agentResolver,
        private PromptBuilderInterface $promptBuilder,
        private StreamMapperInterface $streamMapper,
    ) {
    }

    /**
     * Run the full agent pipeline. Each Message produced is passed through the
     * resolved channel's renderer, and non-null renderings are yielded to the caller.
     *
     * Yields a `Loading` marker before the (blocking) LLM call and a `LoadingStop`
     * before the first real message — text-based renderers may ignore both, the
     * TUI renderer passes them through so the UI can show/hide its loader.
     *
     * If $channel is provided, it is used directly and the resolver is bypassed.
     * This lets surfaces (like ChatCommand) inject a stateful channel built with
     * runtime context (widgets, etc.).
     *
     * @return iterable<mixed>
     */
    public function handle(Request $request, ?ChannelInterface $channel = null): iterable
    {
        $event = $this->eventDispatcher->dispatch(new RequestEvent($request));
        $request = $event->request;

        $channel ??= $this->channelResolver->resolve($request);
        $channelEvent = $this->eventDispatcher->dispatch(new ChannelEvent($channel));
        $channel = $channelEvent->channel;
        $renderer = $channel->getRenderer();

        $message = $channel->receive($request);
        $messageEvent = $this->eventDispatcher->dispatch(new MessageEvent($message));
        $message = $messageEvent->message;

        $agent = $this->agentResolver->resolve($message);
        $agentEvent = $this->eventDispatcher->dispatch(new AgentEvent($agent, $message));
        $agent = $agentEvent->agent;

        $prompt = $this->promptBuilder->build($agent, $message);
        $promptEvent = $this->eventDispatcher->dispatch(new PromptEvent($prompt, $agent, $message));
        $prompt = $promptEvent->prompt;

        $loadingStart = new Message(agent: $agent->name, content: '', type: MessageType::Loading);
        $loadingStop = new Message(agent: $agent->name, content: '', type: MessageType::LoadingStop);

        // Always yield, even nulls — yielding hands control back to the caller (TUI tick,
        // CLI foreach), so widget mutations done in $renderer->render() are flushed and
        // text chunks stream progressively. Callers filter null values.
        yield $renderer->render($loadingStart);

        $answers = [];

        try {
            $result = $agent->call($prompt);
        } catch (\Throwable $e) {
            yield $renderer->render($loadingStop);
            $errorMsg = new Message(agent: $agent->name, content: $e->getMessage(), type: MessageType::Error);
            yield $renderer->render($errorMsg);
            $this->eventDispatcher->dispatch(new TerminateEvent([$errorMsg], $agent, $message));

            return;
        }

        $first = true;
        foreach ($this->streamMapper->map($agent->name, $result) as $msg) {
            if ($first) {
                yield $renderer->render($loadingStop);
                $first = false;
            }
            yield $renderer->render($msg);
            $answers[] = $msg;
        }

        if ($first) {
            // Stream produced nothing — still close the loader so the UI doesn't hang.
            yield $renderer->render($loadingStop);
        }

        $this->eventDispatcher->dispatch(new TerminateEvent($answers, $agent, $message));
    }
}
