<?php

declare(strict_types=1);

namespace MambaAi\Tui;

class AgentCycler
{
    /** @var string[] */
    private array $names;
    private int $index;

    /**
     * @param string[] $names
     */
    public function __construct(array $names, string $current)
    {
        $this->names = array_values($names);
        $idx = array_search($current, $this->names, true);
        $this->index = false === $idx ? 0 : $idx;
    }

    public function current(): string
    {
        return $this->names[$this->index] ?? '';
    }

    public function next(): string
    {
        if ([] === $this->names) {
            return '';
        }
        $this->index = ($this->index + 1) % \count($this->names);

        return $this->names[$this->index];
    }
}
