<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit;

use MambaAi\FolderAgentBuilder;
use MambaAi\Tests\Support\Doubles\FakePlatform;
use MambaAi\Tests\TestCase\FilesystemTestCase;
use MambaAi\Tool\MemoryWriteTool;
use PHPUnit\Framework\Attributes\Test;

final class FolderAgentBuilderTest extends FilesystemTestCase
{
    private static int $counter = 0;

    #[Test]
    public function it_uses_defaults_when_config_yaml_missing(): void
    {
        $path = $this->writeAgentTree('agent', []);
        $builder = new FolderAgentBuilder(new FakePlatform(), 'claude-default-model', defaultStream: false);

        $agent = $builder->build('agent', $path);

        self::assertSame('agent', $agent->name);
        self::assertSame('claude-default-model', $agent->model);
        self::assertSame('anthropic', $agent->provider);
        self::assertSame($path, $agent->folder);
        self::assertSame('', $agent->description);
        self::assertFalse($agent->stream);
        self::assertSame([], $agent->excludedParts);
        self::assertTrue($agent->memory);
    }

    #[Test]
    public function it_reads_config_yaml_overrides(): void
    {
        $yaml = "model: claude-3-haiku-20240307\nprovider: openai\nstream: true\ndescription: test\nexclude_parts:\n  - SkillsSystemPart\nmemory: false\n";
        $path = $this->writeAgentTree('agent', ['config.yaml' => $yaml]);
        $builder = new FolderAgentBuilder(new FakePlatform(), 'should-be-overridden');

        $agent = $builder->build('agent', $path);

        self::assertSame('claude-3-haiku-20240307', $agent->model);
        self::assertSame('openai', $agent->provider);
        self::assertTrue($agent->stream);
        self::assertSame('test', $agent->description);
        self::assertSame(['SkillsSystemPart'], $agent->excludedParts);
        self::assertFalse($agent->memory);
    }

    #[Test]
    public function it_reads_agent_md_and_soul_md(): void
    {
        $path = $this->writeAgentTree('agent', [
            'AGENT.md' => 'tu es un dev',
            'SOUL.md' => 'calme',
        ]);
        $builder = new FolderAgentBuilder(new FakePlatform(), 'm');

        $agent = $builder->build('agent', $path);

        self::assertSame('tu es un dev', $agent->systemPrompt);
        self::assertSame('calme', $agent->soulPrompt);
    }

    #[Test]
    public function it_returns_empty_strings_when_md_files_missing(): void
    {
        $path = $this->writeAgentTree('agent', []);
        $builder = new FolderAgentBuilder(new FakePlatform(), 'm');

        $agent = $builder->build('agent', $path);

        self::assertSame('', $agent->systemPrompt);
        self::assertSame('', $agent->soulPrompt);
        self::assertNull($agent->knowledgeDir);
    }

    #[Test]
    public function it_discovers_knowledge_directory_when_present(): void
    {
        $path = $this->writeAgentTree('agent', [
            'knowledge' => ['intro.md' => '# intro'],
        ]);
        $builder = new FolderAgentBuilder(new FakePlatform(), 'm');

        $agent = $builder->build('agent', $path);

        self::assertNotNull($agent->knowledgeDir);
        self::assertSame(realpath($path.'/knowledge'), realpath($agent->knowledgeDir));
    }

    #[Test]
    public function it_loads_skills_sorted_by_name(): void
    {
        $path = $this->writeAgentTree('agent', [
            'skills' => [
                'zebra.md' => '  zebra body  ',
                'alpha.md' => 'alpha body',
                'mid.md' => 'mid body',
            ],
        ]);
        $builder = new FolderAgentBuilder(new FakePlatform(), 'm');

        $agent = $builder->build('agent', $path);

        self::assertCount(3, $agent->skills);
        self::assertSame('alpha', $agent->skills[0]->name);
        self::assertSame('alpha body', $agent->skills[0]->content);
        self::assertSame('mid', $agent->skills[1]->name);
        self::assertSame('zebra', $agent->skills[2]->name);
        self::assertSame('zebra body', $agent->skills[2]->content); // trimmed
    }

    #[Test]
    public function it_returns_no_skills_when_skills_dir_missing(): void
    {
        $path = $this->writeAgentTree('agent', []);
        $builder = new FolderAgentBuilder(new FakePlatform(), 'm');

        self::assertSame([], $builder->build('agent', $path)->skills);
    }

    #[Test]
    public function it_auto_injects_memory_write_tool_when_memory_enabled(): void
    {
        $path = $this->writeAgentTree('agent', ['config.yaml' => "memory: true\n"]);
        $builder = new FolderAgentBuilder(new FakePlatform(), 'm');

        $agent = $builder->build('agent', $path);

        self::assertCount(1, $agent->tools);
        self::assertInstanceOf(MemoryWriteTool::class, $agent->tools[0]);
    }

    #[Test]
    public function it_omits_memory_write_tool_when_memory_disabled(): void
    {
        $path = $this->writeAgentTree('agent', ['config.yaml' => "memory: false\n"]);
        $builder = new FolderAgentBuilder(new FakePlatform(), 'm');

        self::assertSame([], $builder->build('agent', $path)->tools);
    }

    #[Test]
    public function it_discovers_tools_via_as_tool_attribute_reflection(): void
    {
        $className = $this->uniqueToolClass('Greeter');
        $php = <<<PHP
<?php

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('greet', 'Greet a name')]
final class $className
{
    public function __invoke(string \$name): string
    {
        return 'hi '.\$name;
    }
}
PHP;

        $path = $this->writeAgentTree('agent', [
            'config.yaml' => "memory: false\n",
            'tools' => [$className.'.php' => $php],
        ]);
        $builder = new FolderAgentBuilder(new FakePlatform(), 'm');

        $agent = $builder->build('agent', $path);

        self::assertCount(1, $agent->tools);
        self::assertSame($className, $agent->tools[0]::class);
    }

    #[Test]
    public function it_skips_php_files_without_as_tool_attribute(): void
    {
        $unmarked = $this->uniqueToolClass('Plain');
        $php = <<<PHP
<?php

final class $unmarked
{
    public function __invoke(): void {}
}
PHP;

        $path = $this->writeAgentTree('agent', [
            'config.yaml' => "memory: false\n",
            'tools' => [$unmarked.'.php' => $php],
        ]);
        $builder = new FolderAgentBuilder(new FakePlatform(), 'm');

        self::assertSame([], $builder->build('agent', $path)->tools);
    }

    private function uniqueToolClass(string $prefix): string
    {
        return $prefix.'Tool_'.getmypid().'_'.++self::$counter;
    }
}
