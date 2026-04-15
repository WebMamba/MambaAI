<?php

namespace MambaAi\Version_2;

use Symfony\AI\Agent\Agent as BaseAgent;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;

class Agent
{
    public function __construct(
        public string $name,
        public PlatformInterface $platform,
        public string $model,
        public string $provider,
        public string $folder,
        public string $description,
        public array $tools = [],
        /** @var Skill[] */
        public array $skills = [],
        public bool $stream = true,
        public string $systemPrompt = '',
        public string $soulPrompt = '',
        public ?string $knowledgeDir = null,
        /** @var string[] short class names of parts to exclude for this agent */
        public array $excludedParts = [],
    ) {}

    public function call(Prompt $prompt): iterable
    {
        $processors = [];
        if ($this->tools !== []) {
            $processor = new AgentProcessor(new Toolbox($this->tools));
            $processors = [$processor];
        }

        $agent = new BaseAgent(
            $this->platform,
            $this->model,
            inputProcessors: $processors,
            outputProcessors: $processors,
        );

        $options = array_merge(['stream' => $this->stream], $prompt->options);
        $result = $agent->call($prompt->getMessages(), $options);

        if ($result instanceof StreamResult) {
            foreach ($result->getContent() as $delta) {
                if ($delta instanceof TextDelta) {
                    yield new Message(agent: $this->name, content: $delta->getText());
                }
            }
        } else {
            yield new Message(agent: $this->name, content: (string) $result->getContent());
        }
    }
}
