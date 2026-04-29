<?php

declare(strict_types=1);

namespace MambaAi\Tests\Support\Factory;

use MambaAi\Agent;
use MambaAi\Tests\Support\Doubles\FakePlatform;
use Symfony\AI\Platform\PlatformInterface;

final class AgentFactory
{
    /**
     * @param array<int, object> $tools
     * @param array<int, mixed>  $skills
     * @param string[]           $excludedParts
     */
    public static function make(
        string $name = 'default',
        ?PlatformInterface $platform = null,
        string $model = 'claude-3-haiku-20240307',
        string $provider = 'anthropic',
        string $folder = '/virtual/agents/default',
        string $description = '',
        array $tools = [],
        array $skills = [],
        bool $stream = true,
        string $systemPrompt = '',
        string $soulPrompt = '',
        ?string $knowledgeDir = null,
        array $excludedParts = [],
        bool $memory = false,
    ): Agent {
        return new Agent(
            name: $name,
            platform: $platform ?? new FakePlatform(),
            model: $model,
            provider: $provider,
            folder: $folder,
            description: $description,
            tools: $tools,
            skills: $skills,
            stream: $stream,
            systemPrompt: $systemPrompt,
            soulPrompt: $soulPrompt,
            knowledgeDir: $knowledgeDir,
            excludedParts: $excludedParts,
            memory: $memory,
        );
    }
}
