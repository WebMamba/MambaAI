<?php

namespace MambaAi\Version_2;

interface AgentBuilderInterface
{
    public function build(string $name, string $path): Agent;
}
