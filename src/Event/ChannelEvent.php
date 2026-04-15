<?php

namespace MambaAi\Version_2\Event;

use MambaAi\Version_2\ChannelInterface;

class ChannelEvent
{
    public function __construct(
        public ChannelInterface $channel
    ) {}
}
