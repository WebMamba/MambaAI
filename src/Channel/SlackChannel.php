<?php

namespace MambaAi\Version_2\Channel;

use MambaAi\Version_2\ChannelInterface;
use MambaAi\Version_2\Message;
use MambaAi\Version_2\Renderer\MessageRendererInterface;
use MambaAi\Version_2\Renderer\NullRenderer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SlackChannel implements ChannelInterface
{
    private string $channelId = '';
    private ?string $threadTs = null;
    private string $buffer = '';

    public function __construct(
        private string $botToken,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function getRenderer(): MessageRendererInterface
    {
        return new NullRenderer();
    }

    public function supports(Request $request): bool
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) && (
            isset($payload['api_app_id']) ||
            ($payload['type'] ?? '') === 'url_verification'
        );
    }

    public function receive(Request $request): Message
    {
        $payload = json_decode($request->getContent(), true);

        $event = $payload['event'] ?? [];
        $this->channelId = $event['channel'] ?? '';
        $this->threadTs = $event['thread_ts'] ?? $event['ts'] ?? null;
        $this->buffer = '';

        $agentName = $this->resolveAgentName($this->channelId);
        $text = $this->cleanText($event['text'] ?? '');

        $this->logger->info('[SlackChannel] Message received', [
            'channel_id' => $this->channelId,
            'agent' => $agentName,
            'text' => mb_strimwidth($text, 0, 80, '…'),
        ]);

        return new Message(agent: $agentName, content: $text);
    }

    public function send(string $output): void
    {
        $this->buffer .= $output;
    }

    public function finalize(): void
    {
        if ($this->buffer === '') {
            $this->logger->warning('[SlackChannel] finalize() called but buffer is empty — no response to send', [
                'channel_id' => $this->channelId,
            ]);
            return;
        }

        if ($this->channelId === '') {
            $this->logger->error('[SlackChannel] finalize() called but channelId is empty — cannot send');
            return;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://slack.com/api/chat.postMessage', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->botToken,
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'json' => array_filter([
                    'channel' => $this->channelId,
                    'text' => $this->buffer,
                    'thread_ts' => $this->threadTs,
                ]),
            ]);

            $data = $response->toArray(throw: false);
            if (!($data['ok'] ?? false)) {
                $this->logger->error('[SlackChannel] Slack API error', [
                    'error' => $data['error'] ?? 'unknown',
                    'channel_id' => $this->channelId,
                ]);
            } else {
                $this->logger->info('[SlackChannel] Message sent to Slack', ['channel_id' => $this->channelId]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('[SlackChannel] HTTP error posting to Slack', [
                'message' => $e->getMessage(),
                'channel_id' => $this->channelId,
            ]);
        }

        $this->buffer = '';
    }

    private function resolveAgentName(string $channelId): string
    {
        if ($channelId === '') {
            return 'default';
        }

        try {
            $response = $this->httpClient->request('GET', 'https://slack.com/api/conversations.info', [
                'headers' => ['Authorization' => 'Bearer '.$this->botToken],
                'query' => ['channel' => $channelId],
            ]);

            $data = $response->toArray(throw: false);

            if (!($data['ok'] ?? false)) {
                $this->logger->warning('[SlackChannel] conversations.info failed — falling back to default agent', [
                    'channel_id' => $channelId,
                    'error' => $data['error'] ?? 'unknown',
                ]);
                return 'default';
            }

            return $data['channel']['name'] ?? 'default';
        } catch (\Throwable $e) {
            $this->logger->warning('[SlackChannel] conversations.info exception — falling back to default agent', [
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
            ]);
            return 'default';
        }
    }

    private function cleanText(string $text): string
    {
        // Supprime les mentions @bot (<@UXXXXXXXX>) du début du message
        return trim(preg_replace('/^<@[A-Z0-9]+>\s*/', '', $text));
    }
}
