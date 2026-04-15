<?php

namespace MambaAi\Version_2;

interface AgentLoaderInterface
{
    /** @return iterable<Agent> */
    public function load(): iterable;
}
