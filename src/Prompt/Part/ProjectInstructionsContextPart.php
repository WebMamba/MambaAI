<?php

declare(strict_types=1);

namespace MambaAi\Prompt\Part;

use MambaAi\Agent;
use MambaAi\Message;
use MambaAi\Prompt\SystemPromptPartInterface;

/**
 * Injects project- and user-level instruction files (CLAUDE.md) as a `<context name="codeStyle">` block.
 *
 * Mirrors Claude Code's "codeStyle" context: ancestor instruction files become part of the
 * system prompt so the agent inherits the same conventions as Claude Code in this repo.
 */
final class ProjectInstructionsContextPart implements SystemPromptPartInterface
{
    public function __construct(
        private string $projectDir,
        private ?string $userHomeDir = null,
    ) {
        if (null === $this->userHomeDir) {
            $home = getenv('HOME');
            $this->userHomeDir = false !== $home && '' !== $home ? $home : null;
        }
    }

    #[\Override]
    public function getTargetAgent(): ?string
    {
        return null;
    }

    #[\Override]
    public function getContent(Agent $agent, Message $message): ?string
    {
        $sections = [];

        $projectClaude = $this->projectDir.'/CLAUDE.md';
        if (is_file($projectClaude)) {
            $content = trim((string) file_get_contents($projectClaude));
            if ('' !== $content) {
                $sections[] = '# Project CLAUDE.md ('.$projectClaude.')';
                $sections[] = $content;
            }
        }

        if (null !== $this->userHomeDir) {
            $userClaude = $this->userHomeDir.'/.claude/CLAUDE.md';
            if (is_file($userClaude)) {
                $content = trim((string) file_get_contents($userClaude));
                if ('' !== $content) {
                    if ([] !== $sections) {
                        $sections[] = '';
                    }
                    $sections[] = '# User CLAUDE.md ('.$userClaude.')';
                    $sections[] = $content;
                }
            }
        }

        if ([] === $sections) {
            return null;
        }

        return implode("\n", [
            '<context name="codeStyle">',
            ...$sections,
            '</context>',
        ]);
    }
}
