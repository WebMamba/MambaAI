<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit;

use MambaAi\DefaultStreamMapper;
use MambaAi\Message;
use MambaAi\MessageType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;

final class DefaultStreamMapperTest extends TestCase
{
    #[Test]
    public function it_yields_single_text_message_for_non_stream_result(): void
    {
        $mapper = new DefaultStreamMapper();

        $messages = iterator_to_array($mapper->map('mambi', new TextResult('hello')), false);

        self::assertCount(1, $messages);
        self::assertSame(MessageType::Text, $messages[0]->type);
        self::assertSame('hello', $messages[0]->content);
        self::assertSame('mambi', $messages[0]->agent);
    }

    #[Test]
    public function it_yields_thinking_start_then_delta(): void
    {
        $mapper = new DefaultStreamMapper();
        $stream = new StreamResult($this->gen([
            new ThinkingStart(),
            new ThinkingDelta('réflexion en cours'),
        ]));

        $messages = iterator_to_array($mapper->map('mambi', $stream), false);

        self::assertCount(2, $messages);
        self::assertSame(MessageType::Thinking, $messages[0]->type);
        self::assertSame(['phase' => 'start'], $messages[0]->context);
        self::assertSame(MessageType::Thinking, $messages[1]->type);
        self::assertSame('réflexion en cours', $messages[1]->content);
        self::assertSame(['phase' => 'delta'], $messages[1]->context);
    }

    #[Test]
    public function it_buffers_tool_input_and_flushes_complete_phase_before_text(): void
    {
        $mapper = new DefaultStreamMapper();
        $stream = new StreamResult($this->gen([
            new ToolCallStart('id-1', 'memory_write'),
            new ToolInputDelta('id-1', 'memory_write', '{"content"'),
            new ToolInputDelta('id-1', 'memory_write', ':"hello"}'),
            new TextDelta('done'),
        ]));

        $messages = iterator_to_array($mapper->map('mambi', $stream), false);

        // start, input, input, complete (flushed before text), text
        self::assertCount(5, $messages);

        self::assertSame('start', $messages[0]->context['phase']);
        self::assertSame('input', $messages[1]->context['phase']);
        self::assertSame('{"content"', $messages[1]->context['args']);
        self::assertSame('input', $messages[2]->context['phase']);
        self::assertSame('{"content":"hello"}', $messages[2]->context['args']);

        self::assertSame(MessageType::ToolCall, $messages[3]->type);
        self::assertSame('complete', $messages[3]->context['phase']);
        self::assertSame('{"content":"hello"}', $messages[3]->context['args']);

        self::assertSame(MessageType::Text, $messages[4]->type);
        self::assertSame('done', $messages[4]->content);
    }

    #[Test]
    public function it_emits_pending_tools_as_error_phase_on_exception(): void
    {
        $mapper = new DefaultStreamMapper();
        $stream = new StreamResult($this->gen([
            new ToolCallStart('id-1', 'broken'),
            new ToolInputDelta('id-1', 'broken', '{"x":1}'),
            'BOOM', // sentinel triggers throw inside generator
        ]));

        $messages = iterator_to_array($mapper->map('mambi', $stream), false);

        $errorPhases = array_values(array_filter(
            $messages,
            static fn (Message $m) => ($m->context['phase'] ?? null) === 'error',
        ));

        self::assertNotEmpty($errorPhases, 'expected at least one tool flushed as error');
        self::assertSame('broken', $errorPhases[0]->content);
        self::assertSame('{"x":1}', $errorPhases[0]->context['args']);

        $errorMessages = array_values(array_filter(
            $messages,
            static fn (Message $m) => MessageType::Error === $m->type,
        ));
        self::assertCount(1, $errorMessages);
    }

    #[Test]
    public function it_handles_raw_tool_call_complete_path(): void
    {
        $mapper = new DefaultStreamMapper();
        $stream = new StreamResult($this->gen([
            new ToolCallComplete(new ToolCall('id-1', 'memory_write', ['content' => 'x'])),
        ]));

        $messages = iterator_to_array($mapper->map('mambi', $stream), false);

        self::assertCount(1, $messages);
        self::assertSame(MessageType::ToolCall, $messages[0]->type);
        self::assertSame('complete', $messages[0]->context['phase']);
        self::assertSame('id-1', $messages[0]->context['id']);
        self::assertSame(json_encode(['content' => 'x']), $messages[0]->context['args']);
    }

    #[Test]
    public function it_emits_pending_tool_as_error_when_stream_ends_without_completion(): void
    {
        $mapper = new DefaultStreamMapper();
        $stream = new StreamResult($this->gen([
            new ToolCallStart('id-1', 'never_done'),
            new ToolInputDelta('id-1', 'never_done', '{"a":1}'),
        ]));

        $messages = iterator_to_array($mapper->map('mambi', $stream), false);

        $last = end($messages);
        self::assertSame(MessageType::ToolCall, $last->type);
        self::assertSame('error', $last->context['phase']);
        self::assertSame('never_done', $last->content);
    }

    /**
     * @param iterable<int, mixed> $items
     */
    private function gen(iterable $items): \Generator
    {
        foreach ($items as $item) {
            if ('BOOM' === $item) {
                throw new \RuntimeException('boom');
            }
            yield $item;
        }
    }
}
