<?php

declare(strict_types=1);

namespace MambaAi\Prompt\Part;

use MambaAi\Agent;
use MambaAi\Message;
use MambaAi\Prompt\SystemPromptPartInterface;

final class ReadmeContextPart implements SystemPromptPartInterface
{
    public function __construct(
        private string $projectDir,
    ) {
    }

    #[\Override]
    public function getTargetAgent(): ?string
    {
        return null;
    }

    #[\Override]
    public function getContent(Agent $agent, Message $message): ?string
    {
        $readme = $this->projectDir.'/README.md';
        if (!is_file($readme)) {
            return null;
        }

        $content = trim((string) file_get_contents($readme));
        if ('' === $content) {
            return null;
        }

        return implode("\n", [
            '<context name="readme">',
            $content,
            '</context>',
        ]);
    }
}
