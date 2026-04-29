<?php

declare(strict_types=1);

namespace MambaAi\Prompt\Part;

use MambaAi\Agent;
use MambaAi\Message;
use MambaAi\Prompt\SystemPromptPartInterface;

final class SkillsSystemPart implements SystemPromptPartInterface
{
    #[\Override]
    public function getTargetAgent(): ?string
    {
        return null;
    }

    #[\Override]
    public function getContent(Agent $agent, Message $message): ?string
    {
        if ([] === $agent->skills) {
            return null;
        }

        $lines = [
            '<system-reminder>',
            'The following skills are available:',
        ];

        foreach ($agent->skills as $skill) {
            $lines[] = '';
            $lines[] = '### '.$skill->name;
            $lines[] = $skill->content;
        }

        $lines[] = '</system-reminder>';

        return implode("\n", $lines);
    }
}
