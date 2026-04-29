<?php

declare(strict_types=1);

namespace MambaAi;

use Symfony\AI\Platform\Message\MessageBag;

class Prompt
{
    public function __construct(
        public MessageBag $UserMessages,
        public MessageBag $SystemMessages,
        public array $options,
    ) {
    }

    public function getMessages(): MessageBag
    {
        return $this->UserMessages->merge($this->SystemMessages);
    }
}
