<?php

namespace MambaAi\Version_2\Channel;

use MambaAi\Version_2\ChannelInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

class ChannelResolver implements ChannelResolverInterface
{
    public function __construct(
        /** @var ChannelInterface[] */
        private iterable $channels,
    ) {}

    public function resolve(Request $request): ChannelInterface
    {
        foreach ($this->channels as $channel) {
            if ($channel->supports($request)) {
                return $channel;
            }
        }

        throw new RuntimeException('No channel supports this request.');
    }
}
