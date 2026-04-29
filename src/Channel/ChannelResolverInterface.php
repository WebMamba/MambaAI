<?php

declare(strict_types=1);

namespace MambaAi\Channel;

use MambaAi\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;

interface ChannelResolverInterface
{
    public function resolve(Request $request): ChannelInterface;
}
