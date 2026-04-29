<?php

declare(strict_types=1);

namespace MambaAi;

use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\StreamResult;

class DefaultStreamMapper implements StreamMapperInterface
{
    #[\Override]
    public function map(string $agentName, ResultInterface $result): iterable
    {
        if (!$result instanceof StreamResult) {
            yield new Message(agent: $agentName, content: (string) $result->getContent(), type: MessageType::Text);

            return;
        }

        $pendingTools = [];
        $toolInputBuffers = [];

        try {
            foreach ($result->getContent() as $delta) {
                if ($delta instanceof ThinkingStart) {
                    yield new Message(agent: $agentName, content: '', type: MessageType::Thinking, context: ['phase' => 'start']);
                } elseif ($delta instanceof ThinkingDelta) {
                    yield new Message(agent: $agentName, content: $delta->getThinking(), type: MessageType::Thinking, context: ['phase' => 'delta']);
                } elseif ($delta instanceof TextDelta) {
                    // Symfony AI's StreamListener replaces ToolCallComplete with this TextDelta —
                    // flush pending tool "complete" signals before the response text
                    foreach ($pendingTools as $id => $name) {
                        yield new Message(agent: $agentName, content: $name, type: MessageType::ToolCall, context: ['phase' => 'complete', 'id' => $id, 'args' => $toolInputBuffers[$id] ?? '']);
                    }
                    $pendingTools = [];
                    $toolInputBuffers = [];
                    yield new Message(agent: $agentName, content: $delta->getText(), type: MessageType::Text);
                } elseif ($delta instanceof ToolCallStart) {
                    $id = $delta->getId();
                    $pendingTools[$id] = $delta->getName();
                    $toolInputBuffers[$id] = '';
                    yield new Message(agent: $agentName, content: $delta->getName(), type: MessageType::ToolCall, context: ['phase' => 'start', 'id' => $id]);
                } elseif ($delta instanceof ToolInputDelta) {
                    $id = $delta->getId();
                    $toolInputBuffers[$id] = ($toolInputBuffers[$id] ?? '').$delta->getPartialJson();
                    yield new Message(agent: $agentName, content: $delta->getName(), type: MessageType::ToolCall, context: ['phase' => 'input', 'id' => $id, 'args' => $toolInputBuffers[$id]]);
                } elseif ($delta instanceof ToolCallComplete) {
                    // Normally replaced by StreamListener, but handle the raw case too
                    foreach ($delta->getToolCalls() as $toolCall) {
                        $id = $toolCall->getId();
                        yield new Message(agent: $agentName, content: $toolCall->getName(), type: MessageType::ToolCall, context: ['phase' => 'complete', 'id' => $id, 'args' => json_encode($toolCall->getArguments())]);
                        unset($pendingTools[$id], $toolInputBuffers[$id]);
                    }
                }
            }

            foreach ($pendingTools as $id => $name) {
                yield new Message(agent: $agentName, content: $name, type: MessageType::ToolCall, context: ['phase' => 'error', 'id' => $id, 'args' => $toolInputBuffers[$id] ?? '']);
            }
        } catch (\Throwable $e) {
            foreach ($pendingTools as $id => $name) {
                yield new Message(agent: $agentName, content: $name, type: MessageType::ToolCall, context: ['phase' => 'error', 'id' => $id, 'args' => $toolInputBuffers[$id] ?? '']);
            }
            yield new Message(agent: $agentName, content: $e->getMessage(), type: MessageType::Error);
        }
    }
}
