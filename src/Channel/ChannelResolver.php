<?php

declare(strict_types=1);

namespace MambaAi\Channel;

use MambaAi\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;

class ChannelResolver implements ChannelResolverInterface
{
    public function __construct(
        /** @var ChannelInterface[] */
        private iterable $channels,
    ) {
    }

    #[\Override]
    public function resolve(Request $request): ChannelInterface
    {
        foreach ($this->channels as $channel) {
            if ($channel->supports($request)) {
                return $channel;
            }
        }

        throw new \RuntimeException('No channel supports this request.');
    }
}
