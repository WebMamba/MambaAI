<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit\Channel;

use MambaAi\Channel\SlackChannel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

final class SlackChannelTest extends TestCase
{
    #[Test]
    public function it_supports_payload_with_api_app_id(): void
    {
        $channel = new SlackChannel('xoxb-token', new MockHttpClient());
        self::assertTrue($channel->supports($this->jsonRequest(['api_app_id' => 'A123', 'event' => []])));
    }

    #[Test]
    public function it_supports_url_verification_payload(): void
    {
        $channel = new SlackChannel('xoxb-token', new MockHttpClient());
        self::assertTrue($channel->supports($this->jsonRequest(['type' => 'url_verification', 'challenge' => 'x'])));
    }

    #[Test]
    public function it_rejects_unrelated_payload(): void
    {
        $channel = new SlackChannel('xoxb-token', new MockHttpClient());
        self::assertFalse($channel->supports($this->jsonRequest(['foo' => 'bar'])));
        self::assertFalse($channel->supports(new Request()));
    }

    #[Test]
    public function it_strips_leading_at_mention_in_received_message(): void
    {
        // conversations.info → name 'general'
        $http = new MockHttpClient([
            new MockResponse(json_encode(['ok' => true, 'channel' => ['name' => 'general']])),
        ]);
        $channel = new SlackChannel('xoxb-token', $http);

        $message = $channel->receive($this->jsonRequest([
            'api_app_id' => 'A123',
            'event' => ['channel' => 'C1', 'ts' => '1', 'text' => '<@U999> bonjour le monde'],
        ]));

        self::assertSame('bonjour le monde', $message->content);
        self::assertSame('general', $message->agent);
    }

    #[Test]
    public function it_falls_back_to_default_when_conversations_info_fails(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode(['ok' => false, 'error' => 'not_in_channel'])),
        ]);
        $channel = new SlackChannel('xoxb-token', $http);

        $message = $channel->receive($this->jsonRequest([
            'api_app_id' => 'A123',
            'event' => ['channel' => 'C1', 'ts' => '1', 'text' => 'hi'],
        ]));

        self::assertSame('default', $message->agent);
    }

    #[Test]
    public function it_aggregates_text_chunks_into_buffer_and_posts_once(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode(['ok' => true, 'channel' => ['name' => 'general']])),
            new MockResponse(json_encode(['ok' => true]), ['http_code' => 200]),
        ]);
        $channel = new SlackChannel('xoxb-token', $http);

        $channel->receive($this->jsonRequest([
            'api_app_id' => 'A123',
            'event' => ['channel' => 'C1', 'ts' => '1', 'text' => 'hi'],
        ]));

        // post() reçoit désormais des string déjà rendus par MessageRendererInterface;
        // les Message non-textuels (Loading, etc.) sont filtrés en amont par le kernel.
        $channel->post(['Hello ', 'world!', '[Erreur] oops']);

        self::assertSame(2, $http->getRequestsCount());
    }

    #[Test]
    public function it_skips_post_when_buffer_empty(): void
    {
        // Pre-load conversations.info; if post() tries to call chat.postMessage,
        // MockHttpClient with no further responses will throw.
        $http = new MockHttpClient([
            new MockResponse(json_encode(['ok' => true, 'channel' => ['name' => 'general']])),
        ]);
        $channel = new SlackChannel('xoxb-token', $http);

        $channel->receive($this->jsonRequest([
            'api_app_id' => 'A123',
            'event' => ['channel' => 'C1', 'ts' => '1', 'text' => 'hi'],
        ]));

        // Le renderer aurait laissé tomber les Loading → rien à poster.
        $channel->post([]);

        self::assertSame(1, $http->getRequestsCount());
    }

    private function jsonRequest(array $payload): Request
    {
        return new Request([], [], [], [], [], [], json_encode($payload));
    }
}
