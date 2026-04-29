<?php

declare(strict_types=1);

namespace MambaAi\Prompt;

use MambaAi\Agent;
use MambaAi\Message;
use Symfony\AI\Platform\Message\Content\Text;

interface UserPromptPartInterface extends PromptPartInterface
{
    /**
     * Returns an array of Text blocks to include in the user message.
     * Return an empty array to contribute nothing.
     *
     * @return Text[]
     */
    public function getBlocks(Agent $agent, Message $message): array;
}
