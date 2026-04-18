<?php

namespace MambaAi\Version_2\Channel;

use MambaAi\Version_2\ChannelInterface;
use MambaAi\Version_2\Message;
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
    ) {}

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

        $agentName = $this->resolveAgentName($this->channelId);
        $text = $this->cleanText($event['text'] ?? '');

        return new Message(agent: $agentName, content: $text);
    }

    public function send(Message $message): void
    {
        $this->buffer .= $message->content;
    }

    public function finalize(): void
    {
        if ($this->buffer === '' || $this->channelId === '') {
            return;
        }

        $this->httpClient->request('POST', 'https://slack.com/api/chat.postMessage', [
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

            $data = $response->toArray();

            return $data['channel']['name'] ?? 'default';
        } catch (\Throwable) {
            return 'default';
        }
    }

    private function cleanText(string $text): string
    {
        // Supprime les mentions @bot (<@UXXXXXXXX>) du début du message
        return trim(preg_replace('/^<@[A-Z0-9]+>\s*/', '', $text));
    }
}
