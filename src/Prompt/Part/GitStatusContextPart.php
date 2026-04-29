<?php

declare(strict_types=1);

namespace MambaAi\Prompt\Part;

use MambaAi\Agent;
use MambaAi\Message;
use MambaAi\Prompt\SystemPromptPartInterface;
use Symfony\Component\Process\Process;

final class GitStatusContextPart implements SystemPromptPartInterface
{
    public function __construct(
        private string $projectDir,
        private int $recentCommitsCount = 5,
    ) {
    }

    #[\Override]
    public function getTargetAgent(): ?string
    {
        return null;
    }

    #[\Override]
    public function getContent(Agent $agent, Message $message): ?string
    {
        if (!is_dir($this->projectDir.'/.git')) {
            return null;
        }

        $branch = $this->git(['rev-parse', '--abbrev-ref', 'HEAD']);
        $mainRef = $this->git(['rev-parse', '--abbrev-ref', 'origin/HEAD']);
        $mainBranch = (null !== $mainRef && str_starts_with($mainRef, 'origin/'))
            ? substr($mainRef, 7)
            : null;
        $user = $this->git(['config', 'user.name']);
        $status = $this->git(['status', '--short']);
        $log = $this->git(['log', '--oneline', '-'.$this->recentCommitsCount]);

        $lines = [
            '<context name="gitStatus">',
            'This is the git status at the start of the conversation. Note that this status is a snapshot in time, and will not update during the conversation.',
            '',
            'Current branch: '.($branch ?? 'unknown'),
        ];

        if (null !== $mainBranch && '' !== $mainBranch) {
            $lines[] = 'Main branch (you will usually use this for PRs): '.$mainBranch;
        }
        if (null !== $user && '' !== $user) {
            $lines[] = 'Git user: '.$user;
        }

        $lines[] = '';
        $lines[] = 'Status:';
        $lines[] = (null === $status || '' === $status) ? '(clean)' : $status;
        $lines[] = '';
        $lines[] = 'Recent commits:';
        $lines[] = (null === $log || '' === $log) ? '(none)' : $log;
        $lines[] = '</context>';

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $args
     */
    private function git(array $args): ?string
    {
        $process = new Process(array_merge(['git'], $args), $this->projectDir);
        $process->run();
        if (!$process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput());
    }
}
