<?php

namespace MambaAi\Version_2\Command;

use MambaAi\Version_2\AgentKernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'mamba:chat', description: 'Start an interactive chat session with an agent')]
class ChatCommand extends Command
{
    public function __construct(private AgentKernel $kernel)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('message', InputArgument::OPTIONAL, 'Send a single message and exit')
            ->addOption('agent', 'a', InputOption::VALUE_OPTIONAL, 'Agent name', 'default');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $agent = $input->getOption('agent');

        // Single-shot mode: message passed as argument
        if ($message = $input->getArgument('message')) {
            $this->kernel->handleCliMessage($agent, $message, $output);
            return Command::SUCCESS;
        }

        // Interactive loop mode
        $io->writeln(sprintf('<info>%s</info> — Appuie sur <comment>Ctrl+C</comment> pour quitter.', $agent));
        $io->writeln('');

        while (true) {
            $message = $io->ask('<fg=cyan>Vous</>');

            if ($message === null || trim($message) === '') {
                continue;
            }

            $output->write('<fg=green>' . $agent . '</> : ');
            $this->kernel->handleCliMessage($agent, $message, $output);
            $output->writeln('');
        }
    }
}
