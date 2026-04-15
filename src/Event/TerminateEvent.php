<?php

namespace MambaAi\Version_2\Event;

class TerminateEvent
{
    public function __construct(
        public array $answers
    ) {}
}
