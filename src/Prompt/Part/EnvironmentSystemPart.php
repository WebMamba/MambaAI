<?php

declare(strict_types=1);

namespace MambaAi\Prompt\Part;

use MambaAi\Agent;
use MambaAi\Message;
use MambaAi\Prompt\SystemPromptPartInterface;

final class EnvironmentSystemPart implements SystemPromptPartInterface
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
        $isGitRepo = is_dir($this->projectDir.'/.git');

        return implode("\n", [
            '<env>',
            'Working directory: '.$this->projectDir,
            'Is directory a git repo: '.($isGitRepo ? 'Yes' : 'No'),
            'Platform: '.strtolower(\PHP_OS_FAMILY),
            'Today\'s date: '.date('Y-m-d'),
            'Model: '.$agent->model,
            '</env>',
        ]);
    }
}
