<?php

namespace MambaAi\Version_2;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class FolderAgentBuilder implements AgentBuilderInterface
{
    public function __construct(
        private PlatformInterface $platform,
        private string $defaultModel,
        private bool $defaultStream = true,
    ) {}

    public function build(string $name, string $path): Agent
    {
        $config = $this->readYaml($path, 'config.yaml');

        $knowledgeDir = null;
        foreach ((new Finder())->directories()->in($path)->name('knowledge')->depth(0) as $dir) {
            $knowledgeDir = $dir->getPathname();
            break;
        }

        return new Agent(
            name: $name,
            platform: $this->platform,
            model: $config['model'] ?? $this->defaultModel,
            provider: $config['provider'] ?? 'anthropic',
            folder: $path,
            description: $config['description'] ?? '',
            stream: $config['stream'] ?? $this->defaultStream,
            systemPrompt: $this->readFile($path, 'AGENT.md'),
            soulPrompt: $this->readFile($path, 'SOUL.md'),
            knowledgeDir: $knowledgeDir,
            excludedParts: $config['exclude_parts'] ?? [],
            tools: $this->loadTools($path),
            skills: $this->loadSkills($path),
        );
    }

    private function loadSkills(string $agentPath): array
    {
        if (!is_dir($agentPath.'/skills')) {
            return [];
        }

        $skills = [];
        foreach ((new Finder())->files()->in($agentPath.'/skills')->name('*.md')->depth(0)->sortByName() as $file) {
            $skills[] = new Skill(
                name: $file->getFilenameWithoutExtension(),
                content: trim($file->getContents()),
            );
        }

        return $skills;
    }

    private function loadTools(string $agentPath): array
    {
        if (!is_dir($agentPath.'/tools')) {
            return [];
        }

        $tools = [];
        foreach ((new Finder())->files()->in($agentPath.'/tools')->name('*.php')->depth(0) as $file) {
            $before = get_declared_classes();
            require_once $file->getPathname();
            $newClasses = array_diff(get_declared_classes(), $before);

            foreach ($newClasses as $className) {
                $reflection = new \ReflectionClass($className);
                if ($reflection->getAttributes(AsTool::class)) {
                    $tools[] = new $className();
                }
            }
        }

        return $tools;
    }

    private function readFile(string $path, string $filename): string
    {
        foreach ((new Finder())->files()->in($path)->name($filename)->depth(0) as $file) {
            return $file->getContents();
        }

        return '';
    }

    private function readYaml(string $path, string $filename): array
    {
        foreach ((new Finder())->files()->in($path)->name($filename)->depth(0) as $file) {
            return Yaml::parse($file->getContents()) ?? [];
        }

        return [];
    }
}
