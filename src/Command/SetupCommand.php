<?php

declare(strict_types=1);

namespace MambaAi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
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
            'claude-sonnet-4-5' => 'Claude Sonnet 4.5 — fast and balanced (recommended)',
            'claude-opus-4-5' => 'Claude Opus 4.5 — the most powerful',
            'claude-haiku-4-5' => 'Claude Haiku 4.5 — fastest and cheapest',
        ],
    ];

    public function __construct(private readonly string $projectDir)
    {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('mambaAI configuration');

        // Provider
        $providerQuestion = new ChoiceQuestion('Which LLM provider do you want to use?', ['anthropic'], 'anthropic');
        $provider = (string) $io->askQuestion($providerQuestion);

        // API Key
        $keyQuestion = new Question(\sprintf('%s API key: ', ucfirst($provider)));
        $keyQuestion->setHidden(true);
        $keyQuestion->setValidator(static function (?string $value): string {
            if (null === $value || '' === trim($value)) {
                throw new InvalidArgumentException('The API key cannot be empty.');
            }

            return $value;
        });
        $apiKey = (string) $io->askQuestion($keyQuestion);

        // Model
        $models = array_keys(self::MODELS[$provider]);
        $modelLabels = array_values(self::MODELS[$provider]);
        $modelQuestion = new ChoiceQuestion('Which default model?', $modelLabels, 0);
        $modelLabel = (string) $io->askQuestion($modelQuestion);
        $model = $models[array_search($modelLabel, $modelLabels, true)];

        // Write .env
        $this->writeEnvKey($provider, $apiKey, $io);

        // Write mamba_ai.yaml
        $this->writeConfig($provider, $model, $io);

        $io->success('mambaAI is configured!');
        $io->text([
            'You can now talk to your agents:',
            '  <info>php bin/console mamba:chat mambi</info>',
        ]);

        return Command::SUCCESS;
    }

    private function writeEnvKey(string $provider, string $apiKey, SymfonyStyle $io): void
    {
        $envFile = $this->projectDir.'/.env.local';
        $envKey = match ($provider) {
            'anthropic' => 'ANTHROPIC_API_KEY',
            default => strtoupper($provider).'_API_KEY',
        };

        $line = \sprintf('%s=%s', $envKey, $apiKey);
        $existing = file_exists($envFile) ? file_get_contents($envFile) : '';

        if (str_contains($existing, $envKey.'=')) {
            $updated = preg_replace('/^'.$envKey.'=.*/m', $line, $existing);
            file_put_contents($envFile, $updated);
        } else {
            file_put_contents($envFile, $existing."\n".$line."\n");
        }

        $io->text(\sprintf('  → %s added to <info>.env.local</info>', $envKey));
    }

    private function writeConfig(string $provider, string $model, SymfonyStyle $io): void
    {
        $platformService = match ($provider) {
            'anthropic' => 'anthropic.platform',
            default => $provider.'.platform',
        };

        $configFile = $this->projectDir.'/'.self::CONFIG_FILE;
        $agentsDir = '%kernel.project_dir%/agents';

        $yaml = <<<YAML
mamba_ai:
    agents_dir: '{$agentsDir}'
    default_platform: '{$platformService}'
    default_model: '{$model}'
YAML;

        @mkdir(\dirname($configFile), recursive: true);
        file_put_contents($configFile, $yaml."\n");

        $io->text(\sprintf('  → <info>%s</info> created with model <info>%s</info>', self::CONFIG_FILE, $model));
    }
}
