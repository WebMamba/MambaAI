<?php

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'bash',
    description: <<<DESC
    Execute a shell command and return its output.
    Use this to create files, directories, read file contents, or run any CLI operation.
    Always use absolute paths or paths relative to the project root.
    Examples:
    - Create a file: echo "content" > /path/to/file.md
    - Create a directory: mkdir -p /path/to/dir
    - Read a file: cat /path/to/file
    - List files: ls /path/to/dir
    DESC,
)]
class BashTool
{
    public function __invoke(string $command, int $timeout = 30): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return 'Error: could not start process.';
        }

        fclose($pipes[0]);

        stream_set_timeout($pipes[1], $timeout);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $output = trim((string) $stdout);

        if ($exitCode !== 0 && $stderr) {
            $output .= ($output ? "\n" : '') . 'stderr: ' . trim($stderr);
        }

        return $output !== '' ? $output : '(no output)';
    }
}
