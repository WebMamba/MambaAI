<?php

declare(strict_types=1);

namespace MambaAi\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('memory_write', 'Overwrite the agent\'s long-term memory with updated content. Always write the complete memory, not just new additions.')]
final class MemoryWriteTool
{
    public function __construct(private string $agentFolder)
    {
    }

    public function __invoke(string $content): string
    {
        $dir = $this->agentFolder.'/memory';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($dir.'/MEMORY.md', $content);

        return 'Memory updated.';
    }
}
