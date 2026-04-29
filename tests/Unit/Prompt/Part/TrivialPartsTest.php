<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit\Prompt\Part;

use MambaAi\Prompt\Part\AgentSystemPart;
use MambaAi\Prompt\Part\CurrentDatePart;
use MambaAi\Prompt\Part\MemoryInstructionSystemPart;
use MambaAi\Prompt\Part\MessageContentPart;
use MambaAi\Prompt\Part\SkillsSystemPart;
use MambaAi\Prompt\Part\SoulSystemPart;
use MambaAi\Skill;
use MambaAi\Tests\Support\Factory\AgentFactory;
use MambaAi\Tests\Support\Factory\MessageFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Content\Text;

final class TrivialPartsTest extends TestCase
{
    #[Test]
    public function agent_system_part_returns_prompt_or_null_when_empty(): void
    {
        $part = new AgentSystemPart();

        self::assertNull($part->getContent(AgentFactory::make(systemPrompt: ''), MessageFactory::make()));
        self::assertSame(
            'tu es Mambi',
            $part->getContent(AgentFactory::make(systemPrompt: 'tu es Mambi'), MessageFactory::make()),
        );
        self::assertNull($part->getTargetAgent());
    }

    #[Test]
    public function soul_system_part_returns_prompt_or_null_when_empty(): void
    {
        $part = new SoulSystemPart();

        self::assertNull($part->getContent(AgentFactory::make(soulPrompt: ''), MessageFactory::make()));
        self::assertSame(
            'âme calme',
            $part->getContent(AgentFactory::make(soulPrompt: 'âme calme'), MessageFactory::make()),
        );
    }

    #[Test]
    public function skills_system_part_returns_null_when_no_skills(): void
    {
        $part = new SkillsSystemPart();

        self::assertNull($part->getContent(AgentFactory::make(skills: []), MessageFactory::make()));
    }

    #[Test]
    public function skills_system_part_renders_markdown_with_system_reminder(): void
    {
        $part = new SkillsSystemPart();
        $skills = [
            new Skill(name: 'cook', content: 'how to cook'),
            new Skill(name: 'sing', content: 'how to sing'),
        ];

        $content = $part->getContent(AgentFactory::make(skills: $skills), MessageFactory::make());

        self::assertNotNull($content);
        self::assertStringStartsWith('<system-reminder>', $content);
        self::assertStringEndsWith('</system-reminder>', $content);
        self::assertStringContainsString('### cook', $content);
        self::assertStringContainsString('how to cook', $content);
        self::assertStringContainsString('### sing', $content);
        self::assertStringContainsString('how to sing', $content);
    }

    #[Test]
    public function memory_instruction_part_returns_content_only_when_memory_enabled(): void
    {
        $part = new MemoryInstructionSystemPart();

        self::assertNull($part->getContent(AgentFactory::make(memory: false), MessageFactory::make()));

        $enabled = $part->getContent(AgentFactory::make(memory: true), MessageFactory::make());
        self::assertNotNull($enabled);
        self::assertStringContainsString('Memory instructions', $enabled);
        self::assertStringContainsString('memory_write', $enabled);
    }

    #[Test]
    public function current_date_part_uses_y_m_d_h_i_s_format(): void
    {
        $part = new CurrentDatePart();

        $content = $part->getContent(AgentFactory::make(), MessageFactory::make());

        self::assertNotNull($content);
        self::assertMatchesRegularExpression(
            '/Current date and time: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\./',
            $content,
        );
    }

    #[Test]
    public function message_content_part_wraps_text_in_text_block(): void
    {
        $part = new MessageContentPart();

        $blocks = $part->getBlocks(AgentFactory::make(), MessageFactory::make(content: 'salut'));

        self::assertCount(1, $blocks);
        self::assertInstanceOf(Text::class, $blocks[0]);
        self::assertSame('salut', $blocks[0]->getText());
    }

    #[Test]
    public function all_trivial_parts_apply_to_any_agent(): void
    {
        $parts = [
            new AgentSystemPart(),
            new SoulSystemPart(),
            new SkillsSystemPart(),
            new MemoryInstructionSystemPart(),
            new CurrentDatePart(),
            new MessageContentPart(),
        ];

        foreach ($parts as $part) {
            self::assertNull($part->getTargetAgent(), $part::class.' should target all agents');
        }
    }
}
