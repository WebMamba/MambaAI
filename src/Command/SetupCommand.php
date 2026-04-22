<?php

namespace MambaAi\Version_2\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'mamba:setup', description: 'Configure mambaAI (provider, API key, default model)')]
class SetupCommand extends Command
{
    private const CONFIG_FILE = 'config/packages/mamba_ai.yaml';

    private const MODELS = [
        'anthropic' => [
            'claude-sonnet-4-5' => 'Claude Sonnet 4.5 — rapide et équilibré (recommandé)',
            'claude-opus-4-5'   => 'Claude Opus 4.5 — le plus puissant',
            'claude-haiku-4-5'  => 'Claude Haiku 4.5 — le plus rapide et économique',
        ],
    ];

    public function __construct(private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Configuration de mambaAI');

        $helper = $this->getHelper('question');

        // Provider
        $providerQuestion = new ChoiceQuestion('Quel provider LLM veux-tu utiliser ?', ['anthropic'], 'anthropic');
        $provider = $helper->ask($input, $output, $providerQuestion);

        // API Key
        $keyQuestion = new Question(sprintf('Clé API %s : ', ucfirst($provider)));
        $keyQuestion->setHidden(true);
        $keyQuestion->setValidator(function (?string $value): string {
            if (empty(trim((string) $value))) {
                throw new \RuntimeException('La clé API ne peut pas être vide.');
            }
            return $value;
        });
        $apiKey = $helper->ask($input, $output, $keyQuestion);

        // Model
        $models = array_keys(self::MODELS[$provider]);
        $modelLabels = array_values(self::MODELS[$provider]);
        $modelQuestion = new ChoiceQuestion('Quel modèle par défaut ?', $modelLabels, 0);
        $modelLabel = $helper->ask($input, $output, $modelQuestion);
        $model = $models[array_search($modelLabel, $modelLabels)];

        // Write .env
        $this->writeEnvKey($provider, $apiKey, $io);

        // Write mamba_ai.yaml
        $this->writeConfig($provider, $model, $io);

        $io->success('mambaAI est configuré !');
        $io->text([
            'Tu peux maintenant parler à tes agents :',
            '  <info>php bin/console mamba:chat mambi</info>',
        ]);

        return Command::SUCCESS;
    }

    private function writeEnvKey(string $provider, string $apiKey, SymfonyStyle $io): void
    {
        $envFile = $this->projectDir . '/.env.local';
        $envKey = match ($provider) {
            'anthropic' => 'ANTHROPIC_API_KEY',
            default     => strtoupper($provider) . '_API_KEY',
        };

        $line = sprintf('%s=%s', $envKey, $apiKey);
        $existing = file_exists($envFile) ? file_get_contents($envFile) : '';

        if (str_contains($existing, $envKey . '=')) {
            $updated = preg_replace('/^' . $envKey . '=.*/m', $line, $existing);
            file_put_contents($envFile, $updated);
        } else {
            file_put_contents($envFile, $existing . "\n" . $line . "\n");
        }

        $io->text(sprintf('  → %s ajouté dans <info>.env.local</info>', $envKey));
    }

    private function writeConfig(string $provider, string $model, SymfonyStyle $io): void
    {
        $platformService = match ($provider) {
            'anthropic' => 'anthropic.platform',
            default     => $provider . '.platform',
        };

        $configFile = $this->projectDir . '/' . self::CONFIG_FILE;
        $agentsDir = '%kernel.project_dir%/agents';

        $yaml = <<<YAML
mamba_ai:
    agents_dir: '{$agentsDir}'
    default_platform: '{$platformService}'
    default_model: '{$model}'
YAML;

        @mkdir(dirname($configFile), recursive: true);
        file_put_contents($configFile, $yaml . "\n");

        $io->text(sprintf('  → <info>%s</info> créé avec le modèle <info>%s</info>', self::CONFIG_FILE, $model));
    }
}
