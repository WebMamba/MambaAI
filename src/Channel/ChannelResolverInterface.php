<?php

namespace MambaAi\Version_2\Channel;

use MambaAi\Version_2\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;

interface ChannelResolverInterface
{
    public function resolve(Request $request): ChannelInterface;
}
