<?php

declare(strict_types=1);

namespace MambaAi\Event;

use MambaAi\ChannelInterface;

class ChannelEvent
{
    public function __construct(
        public ChannelInterface $channel,
    ) {
    }
}
