<?php

namespace MambaAi\Version_2\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'mamba:welcome', description: 'Bienvenue dans mambaAI — crée Mambi, ton premier agent')]
class WelcomeCommand extends Command
{
    private const MAMBI_TEMPLATE_DIR = __DIR__ . '/../Resources/agent-templates/mambi';

    public function __construct(
        private readonly string $agentsDir,
        private readonly string $projectDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        parent::__construct();
    }

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
        $io->writeln('  Bienvenue ! Tu vas construire ta propre équipe d\'agents IA.');
        $io->writeln('');

        // Step 1: setup if config missing
        if (!$this->isConfigured()) {
            $io->section('Étape 1/2 — Configuration');
            $io->text('Commençons par configurer ton provider et ta clé API.');
            $io->writeln('');

            $setupCommand = $this->getApplication()->find('mamba:setup');
            $setupResult = $setupCommand->run(new ArrayInput([]), $output);

            if ($setupResult !== Command::SUCCESS) {
                return Command::FAILURE;
            }

            $io->writeln('');
        }

        // Step 2: create mambi
        $io->section('Étape 2/2 — Création de Mambi');
        $mambiDir = $this->agentsDir . '/mambi';

        if (is_dir($mambiDir)) {
            $io->text('Mambi est déjà là ! Tu peux lui parler :');
        } else {
            $io->text([
                'On va créer <info>Mambi</info>, ton premier agent.',
                'Il connaît mambaAI sur le bout des doigts et sera là pour t\'accompagner.',
                '',
            ]);

            $this->copyMambi($mambiDir);
            $io->text('  → Agent <info>mambi</info> créé dans <info>' . $this->agentsDir . '/mambi</info>');
            $io->writeln('');
        }

        $io->success('Tout est prêt !');
        $io->writeln('  Parle à Mambi pour démarrer :');
        $io->writeln('');
        $io->writeln('  <info>php bin/console mamba:chat --agent=mambi "Bonjour ! Comment je crée mon premier agent ?"</info>');
        $io->writeln('');
        $io->writeln('  Autres commandes utiles :');
        $io->writeln('  <comment>php bin/console mamba:agent:create <nom></comment>  — créer un nouvel agent');
        $io->writeln('  <comment>php bin/console mamba:setup</comment>               — reconfigurer le framework');
        $io->writeln('');

        return Command::SUCCESS;
    }

    private function isConfigured(): bool
    {
        return file_exists($this->projectDir . '/config/packages/mamba_ai.yaml');
    }

    private function copyMambi(string $targetDir): void
    {
        $this->filesystem->mkdir($targetDir);
        $this->filesystem->mkdir($targetDir . '/skills');
        $this->filesystem->mkdir($targetDir . '/knowledge');
        $this->filesystem->mkdir($targetDir . '/memory');

        // Root files
        $rootFinder = (new Finder())->files()->in(self::MAMBI_TEMPLATE_DIR)->depth(0);
        foreach ($rootFinder as $file) {
            file_put_contents($targetDir . '/' . $file->getFilename(), $file->getContents());
        }

        // Skills
        $skillsFinder = (new Finder())->files()->in(self::MAMBI_TEMPLATE_DIR . '/skills')->depth(0);
        foreach ($skillsFinder as $file) {
            file_put_contents($targetDir . '/skills/' . $file->getFilename(), $file->getContents());
        }

        // Tools
        $this->filesystem->mkdir($targetDir . '/tools');
        $toolsFinder = (new Finder())->files()->in(self::MAMBI_TEMPLATE_DIR . '/tools')->depth(0);
        foreach ($toolsFinder as $file) {
            file_put_contents($targetDir . '/tools/' . $file->getFilename(), $file->getContents());
        }
    }
}
