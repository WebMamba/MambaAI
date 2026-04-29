<?php

declare(strict_types=1);

namespace MambaAi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'mamba:agent:create', description: 'Create a new agent with its base structure')]
class AgentCreateCommand extends Command
{
    private const TEMPLATES_DIR = __DIR__.'/../Resources/agent-templates/default';

    public function __construct(
        private readonly string $agentsDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Agent name (e.g. developer, assistant, support)');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = strtolower(trim($input->getArgument('name')));

        if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
            $io->error('The name may only contain lowercase letters, digits, dashes or underscores.');

            return Command::FAILURE;
        }

        $targetDir = $this->agentsDir.'/'.$name;

        if (is_dir($targetDir)) {
            $io->error(\sprintf("An agent '%s' already exists in %s", $name, $this->agentsDir));

            return Command::FAILURE;
        }

        $this->copyTemplate(self::TEMPLATES_DIR, $targetDir, $name);

        $io->success(\sprintf("Agent '%s' has been created!", $name));
        $io->listing([
            \sprintf('<info>%s/AGENT.md</info>  ← define its role and instructions', $name),
            \sprintf('<info>%s/SOUL.md</info>   ← give it a personality', $name),
            \sprintf('<info>%s/config.yaml</info> ← configure its model', $name),
        ]);
        $io->text([
            'When you\'re ready, talk to it:',
            \sprintf('  <info>php bin/console mamba:chat --agent=%s "Hello!"</info>', $name),
        ]);

        return Command::SUCCESS;
    }

    private function copyTemplate(string $sourceDir, string $targetDir, string $name): void
    {
        $this->filesystem->mkdir($targetDir);
        $this->filesystem->mkdir($targetDir.'/skills');
        $this->filesystem->mkdir($targetDir.'/knowledge');
        $this->filesystem->mkdir($targetDir.'/tools');
        $this->filesystem->mkdir($targetDir.'/memory');

        $finder = (new Finder())->files()->in($sourceDir)->depth(0);
        foreach ($finder as $file) {
            $content = str_replace('{{name}}', ucfirst($name), $file->getContents());
            file_put_contents($targetDir.'/'.$file->getFilename(), $content);
        }
    }
}
