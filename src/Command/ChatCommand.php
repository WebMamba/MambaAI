<?php

namespace MambaAi\Version_2\Command;

use MambaAi\Version_2\AgentKernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'mamba:chat', description: 'Send a message to an agent')]
class ChatCommand extends Command
{
    public function __construct(private AgentKernel $kernel)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('message', InputArgument::REQUIRED, 'Message to send to the agent')
            ->addOption('agent', 'a', InputOption::VALUE_OPTIONAL, 'Agent name', 'default');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->kernel->handleCli($input);

        return Command::SUCCESS;
    }
}
