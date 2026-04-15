<?php

namespace MambaAi\Version_2\Event;

use Symfony\Component\HttpFoundation\Request;

class RequestEvent
{
    public function __construct(
        public Request $request
    ) {}
}
