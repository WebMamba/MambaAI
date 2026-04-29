<?php

declare(strict_types=1);

namespace MambaAi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'mamba:welcome', description: 'Welcome to mambaAI — create Mambi, your first agent')]
class WelcomeCommand extends Command
{
    private const MAMBI_TEMPLATE_DIR = __DIR__.'/../Resources/agent-templates/mambi';

    public function __construct(
        private readonly string $agentsDir,
        private readonly string $projectDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->writeln('');
        $io->writeln('  <fg=bright-magenta>          __  __                 _          _    ___ </>');
        $io->writeln('  <fg=bright-magenta>         |  \/  |               | |        / \  |_ _|</>');
        $io->writeln('  <fg=bright-magenta>         | |\/| | __ _ _ __ ___ | |__     / _ \  | | </>');
        $io->writeln('  <fg=bright-magenta>         | |  | |/ _` | \'_ ` _ \| \'_ \   / ___ \ | | </>');
        $io->writeln('  <fg=bright-magenta>         |_|  |_|\__,_|_| |_| |_|_.__/  /_/   \_\___|</>');
        $io->writeln('');
        $io->writeln('  Welcome! You\'re about to build your own team of AI agents.');
        $io->writeln('');

        // Step 1: setup if config missing
        if (!$this->isConfigured()) {
            $io->section('Step 1/2 — Configuration');
            $io->text('Let\'s start by configuring your provider and API key.');
            $io->writeln('');

            $setupCommand = $this->getApplication()->find('mamba:setup');
            $setupResult = $setupCommand->run(new ArrayInput([]), $output);

            if (Command::SUCCESS !== $setupResult) {
                return Command::FAILURE;
            }

            $io->writeln('');
        }

        // Step 2: create mambi
        $io->section('Step 2/2 — Creating Mambi');
        $mambiDir = $this->agentsDir.'/mambi';

        if (is_dir($mambiDir)) {
            $io->text('Mambi is already here! You can talk to him:');
        } else {
            $io->text([
                'Let\'s create <info>Mambi</info>, your first agent.',
                'He knows mambaAI inside out and will be there to guide you.',
                '',
            ]);

            $this->copyMambi($mambiDir);
            $io->text('  → Agent <info>mambi</info> created in <info>'.$this->agentsDir.'/mambi</info>');
            $io->writeln('');
        }

        $io->success('All set!');
        $io->writeln('  Talk to Mambi to get started:');
        $io->writeln('');
        $io->writeln('  <info>php bin/console mamba:chat --agent=mambi "Hi! How do I create my first agent?"</info>');
        $io->writeln('');
        $io->writeln('  Other useful commands:');
        $io->writeln('  <comment>php bin/console mamba:agent:create <name></comment>  — create a new agent');
        $io->writeln('  <comment>php bin/console mamba:setup</comment>                — reconfigure the framework');
        $io->writeln('');

        return Command::SUCCESS;
    }

    private function isConfigured(): bool
    {
        return file_exists($this->projectDir.'/config/packages/mamba_ai.yaml');
    }

    private function copyMambi(string $targetDir): void
    {
        $this->filesystem->mkdir($targetDir);
        $this->filesystem->mkdir($targetDir.'/skills');
        $this->filesystem->mkdir($targetDir.'/knowledge');
        $this->filesystem->mkdir($targetDir.'/memory');

        // Root files
        $rootFinder = (new Finder())->files()->in(self::MAMBI_TEMPLATE_DIR)->depth(0);
        foreach ($rootFinder as $file) {
            file_put_contents($targetDir.'/'.$file->getFilename(), $file->getContents());
        }

        // Skills
        $skillsFinder = (new Finder())->files()->in(self::MAMBI_TEMPLATE_DIR.'/skills')->depth(0);
        foreach ($skillsFinder as $file) {
            file_put_contents($targetDir.'/skills/'.$file->getFilename(), $file->getContents());
        }

        // Tools
        $this->filesystem->mkdir($targetDir.'/tools');
        $toolsFinder = (new Finder())->files()->in(self::MAMBI_TEMPLATE_DIR.'/tools')->depth(0);
        foreach ($toolsFinder as $file) {
            file_put_contents($targetDir.'/tools/'.$file->getFilename(), $file->getContents());
        }
    }
}
