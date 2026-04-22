<?php

namespace MambaAi\Version_2;

use Symfony\AI\Agent\Agent as BaseAgent;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;

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
        public bool $memory = true,
    ) {}

    public function call(Prompt $prompt): ResultInterface
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

        return $agent->call($prompt->getMessages(), $options);
    }
}
