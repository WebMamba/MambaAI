<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit\Channel;

use MambaAi\Channel\CliChannel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class CliChannelTest extends TestCase
{
    #[Test]
    public function it_supports_cli_request_only(): void
    {
        $channel = new CliChannel();

        $cli = new Request();
        $cli->attributes->set('_channel', 'cli');

        self::assertTrue($channel->supports($cli));
        self::assertFalse($channel->supports(new Request()));
    }

    #[Test]
    public function it_maps_request_attributes_to_message(): void
    {
        $channel = new CliChannel();

        $request = new Request();
        $request->attributes->set('_channel', 'cli');
        $request->attributes->set('_agent', 'mambi');
        $request->attributes->set('_content', 'salut');

        $message = $channel->receive($request);

        self::assertSame('mambi', $message->agent);
        self::assertSame('salut', $message->content);
    }

    #[Test]
    public function it_falls_back_to_default_agent_and_empty_content(): void
    {
        $channel = new CliChannel();

        $message = $channel->receive(new Request());

        self::assertSame('default', $message->agent);
        self::assertSame('', $message->content);
    }
}
