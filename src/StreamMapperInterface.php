<?php

namespace MambaAi\Version_2;

use Symfony\AI\Platform\Result\ResultInterface;

interface StreamMapperInterface
{
    /**
     * Maps a raw platform result (stream or not) to mambaAI Messages.
     *
     * @return iterable<Message>
     */
    public function map(string $agentName, ResultInterface $result): iterable;
}
