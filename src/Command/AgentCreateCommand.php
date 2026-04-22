<?php

namespace MambaAi\Version_2\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'mamba:agent:create', description: "Crée un nouvel agent avec sa structure de base")]
class AgentCreateCommand extends Command
{
    private const TEMPLATES_DIR = __DIR__ . '/../Resources/agent-templates/default';

    public function __construct(
        private readonly string $agentsDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, "Nom de l'agent (ex: developer, assistant, support)");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = strtolower(trim($input->getArgument('name')));

        if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
            $io->error('Le nom doit contenir uniquement des lettres minuscules, chiffres, tirets ou underscores.');
            return Command::FAILURE;
        }

        $targetDir = $this->agentsDir . '/' . $name;

        if (is_dir($targetDir)) {
            $io->error(sprintf("Un agent '%s' existe déjà dans %s", $name, $this->agentsDir));
            return Command::FAILURE;
        }

        $this->copyTemplate(self::TEMPLATES_DIR, $targetDir, $name);

        $io->success(sprintf("L'agent '%s' a été créé !", $name));
        $io->listing([
            sprintf('<info>%s/AGENT.md</info>  ← définis son rôle et ses instructions', $name),
            sprintf('<info>%s/SOUL.md</info>   ← donne-lui une personnalité', $name),
            sprintf('<info>%s/config.yaml</info> ← configure son modèle', $name),
        ]);
        $io->text([
            'Quand tu es prêt, parle-lui :',
            sprintf('  <info>php bin/console mamba:chat --agent=%s "Bonjour !"</info>', $name),
        ]);

        return Command::SUCCESS;
    }

    private function copyTemplate(string $sourceDir, string $targetDir, string $name): void
    {
        $this->filesystem->mkdir($targetDir);
        $this->filesystem->mkdir($targetDir . '/skills');
        $this->filesystem->mkdir($targetDir . '/knowledge');
        $this->filesystem->mkdir($targetDir . '/tools');
        $this->filesystem->mkdir($targetDir . '/memory');

        $finder = (new Finder())->files()->in($sourceDir)->depth(0);
        foreach ($finder as $file) {
            $content = str_replace('{{name}}', ucfirst($name), $file->getContents());
            file_put_contents($targetDir . '/' . $file->getFilename(), $content);
        }
    }
}
