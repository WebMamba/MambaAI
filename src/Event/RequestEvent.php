<?php

declare(strict_types=1);

namespace MambaAi\Event;

use Symfony\Component\HttpFoundation\Request;

class RequestEvent
{
    public function __construct(
        public Request $request,
    ) {
    }
}
