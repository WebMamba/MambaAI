<?php

namespace MambaAi\Version_2\Prompt\Part;

use MambaAi\Version_2\Agent;
use MambaAi\Version_2\Message;
use MambaAi\Version_2\Prompt\SystemPromptPartInterface;

final class SkillsSystemPart implements SystemPromptPartInterface
{
    public function getTargetAgent(): ?string
    {
        return null;
    }

    public function getContent(Agent $agent, Message $message): ?string
    {
        if ($agent->skills === []) {
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
