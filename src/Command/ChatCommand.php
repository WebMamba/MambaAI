<?php

declare(strict_types=1);

namespace MambaAi\Command;

use MambaAi\AgentKernel;
use MambaAi\AgentLoaderInterface;
use MambaAi\AgentResolverInterface;
use MambaAi\Channel\TuiChannel;
use MambaAi\Message;
use MambaAi\Renderer\TuiRenderer;
use MambaAi\Tui\AgentCycler;
use MambaAi\Tui\StreamAnimator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\CancellableLoaderWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\EditorWidget;
use Symfony\Component\Tui\Widget\LoaderWidget;
use Symfony\Component\Tui\Widget\MarkdownWidget;

use function Amp\async;

#[AsCommand(name: 'mamba:chat', description: 'Start an interactive chat session with an agent')]
class ChatCommand extends Command
{
    private const VERBS = [
        'Accomplishing', 'Brewing', 'Computing', 'Cogitating', 'Crafting',
        'Generating', 'Pondering', 'Processing', 'Ruminating', 'Thinking',
        'Working', 'Deliberating', 'Musing', 'Calculating', 'Inferring',
        'Conjuring', 'Synthesizing', 'Considering', 'Marinating', 'Vibing',
    ];

    public function __construct(
        private AgentKernel $kernel,
        private AgentResolverInterface $agentResolver,
        private AgentLoaderInterface $agentLoader,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('message', InputArgument::OPTIONAL, 'Send a single message and exit')
            ->addOption('agent', 'a', InputOption::VALUE_OPTIONAL, 'Agent name', 'default');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agentName = $input->getOption('agent');

        if ($oneShotMessage = $input->getArgument('message')) {
            return $this->runOneShot($agentName, $oneShotMessage, $output);
        }

        return $this->runInteractive($agentName);
    }

    private function runOneShot(string $agentName, string $message, OutputInterface $output): int
    {
        foreach ($this->kernel->handle($this->buildRequest($agentName, $message, 'cli')) as $rendered) {
            if (\is_string($rendered)) {
                $output->write($rendered);
            }
        }
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function runInteractive(string $agentName): int
    {
        $agent = $this->agentResolver->resolve(new Message(agent: $agentName, content: ''));
        $agentNames = [];
        foreach ($this->agentLoader->load() as $a) {
            $agentNames[] = $a->name;
        }
        $cycler = new AgentCycler($agentNames, $agent->name);
        $verb = self::VERBS[array_rand(self::VERBS)];

        [$tui, $conversationWidget, $loader, $toolText, $editor, $statusText] = $this->buildTui($agent->name, $verb);

        $renderer = new TuiRenderer($tui, $conversationWidget, $loader, $toolText, $editor, $statusText);
        $tuiChannel = new TuiChannel($renderer);
        $animator = new StreamAnimator($loader, $toolText, $verb);

        /** @var ?\Amp\Future $future */
        $future = null;

        $editor->onInput(static function (string $data) use ($cycler, $renderer): bool {
            if ("\t" === $data) {
                $renderer->setAgentPrompt($cycler->next());

                return true;
            }

            return false;
        });

        $loader->onCancel(static function () use (&$future, $animator, $renderer, $tui, $editor): void {
            if (null !== $future) {
                $future->ignore(); // detach the result; the Fiber finishes on its own
                $future = null;
            }
            $animator->stop();
            $renderer->markInterrupted();
            $renderer->setStreaming(false);
            $tui->setFocus($editor);
        });

        $editor->onSubmit(function () use (&$future, $animator, $renderer, $tuiChannel, $cycler, $editor, $loader, $tui): void {
            $msg = trim($editor->getText());
            if ('' === $msg) {
                return;
            }
            $editor->setText('');
            $renderer->appendUserMessage($msg);
            $renderer->setStreaming(true);
            $animator->start();
            $loader->start();
            $tui->setFocus($loader);

            // Run the kernel inside an Amp Fiber. AmpHttpClient (configured in DI)
            // suspends the Fiber during HTTP I/O, so the Revolt event loop keeps
            // firing TUI ticks → animations stay smooth.
            $future = async(function () use ($cycler, $msg, $tuiChannel): void {
                foreach ($this->kernel->handle($this->buildRequest($cycler->current(), $msg, 'tui'), $tuiChannel) as $_) {
                    // TuiRenderer mutates widgets as a side effect — nothing to do with the yielded value.
                }
            });
        });

        $tui->onTick(static function () use (&$future, $animator, $renderer) {
            $animator->tick($renderer->getCurrentTool());

            if (null === $future) {
                return false;
            }

            if ($future->isComplete()) {
                try {
                    $future->await(); // re-throws any exception raised inside the Fiber
                } catch (\Throwable $e) {
                    $renderer->appendError($e->getMessage());
                }
                $animator->stop();
                $renderer->finalizeAssistantTurn();
                $renderer->setStreaming(false);
                $future = null;

                return false;
            }

            return true; // busy → ticks at 10ms
        });

        $tui->run();

        return Command::SUCCESS;
    }

    private function buildRequest(string $agentName, string $content, string $channel): HttpRequest
    {
        $request = new HttpRequest();
        $request->attributes->set('_channel', $channel);
        $request->attributes->set('_agent', $agentName);
        $request->attributes->set('_content', $content);

        return $request;
    }

    /**
     * @return array{0: Tui, 1: MarkdownWidget, 2: CancellableLoaderWidget, 3: MarkdownWidget, 4: EditorWidget, 5: MarkdownWidget}
     */
    private function buildTui(string $agentName, string $verb): array
    {
        $spinnerFrames = ['·', '✢', '✳', '∗', '✻', '✽'];
        LoaderWidget::addSpinner('claude', array_merge($spinnerFrames, array_reverse($spinnerFrames)));

        $conversationWidget = new MarkdownWidget('');

        $loader = new CancellableLoaderWidget('  '.$verb.'…');
        $loader->setSpinner('claude');
        $loader->setIntervalMs(120);
        $loader->stop();

        $conversationContainer = new ContainerWidget();
        $conversationContainer->expandVertically(true);
        $conversationContainer->add($conversationWidget);

        $editor = new EditorWidget();
        $editor->setMinVisibleLines(1);
        $editor->setMaxVisibleLines(5);
        $editor->setPrompt('  '.$agentName.' ❯  ');

        $toolText = new MarkdownWidget('');
        $statusText = new MarkdownWidget('');

        $stylesheet = new StyleSheet();
        $stylesheet->addRule('Symfony\Component\Tui\Widget\CancellableLoaderWidget::spinner', new Style(color: Color::hex('#FF8C00'), bold: true));
        $stylesheet->addRule('Symfony\Component\Tui\Widget\EditorWidget::frame', new Style(color: Color::hex('#FF8C00')));

        $tui = new Tui(styleSheet: $stylesheet);
        $tui->add($conversationContainer);
        $tui->add($loader);
        $tui->add($toolText);
        $tui->add($editor);
        $tui->add($statusText);
        $tui->setFocus($editor);
        $tui->quitOn('ctrl+c');

        return [$tui, $conversationWidget, $loader, $toolText, $editor, $statusText];
    }
}
