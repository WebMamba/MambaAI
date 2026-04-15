<?php

namespace MambaAi\Version_2;

class Message
{
    public function __construct(
        public string $agent,
        public string $content,
        public array $context = [],
    ) {}
}
