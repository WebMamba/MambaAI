<?php

namespace MambaAi\Version_2;

use Symfony\Component\Finder\Finder;

class AgentLoader implements AgentLoaderInterface
{
    public function __construct(
        private AgentBuilderInterface $builder,
        private string $agentsDir,
    ) {}

    /** @return iterable<Agent> */
    public function load(): iterable
    {
        if (!is_dir($this->agentsDir)) {
            return;
        }

        $finder = (new Finder())->directories()->in($this->agentsDir)->depth(0);

        foreach ($finder as $dir) {
            yield $this->builder->build($dir->getFilename(), $dir->getPathname());
        }
    }
}
