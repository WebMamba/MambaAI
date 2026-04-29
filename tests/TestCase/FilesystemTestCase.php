<?php

declare(strict_types=1);

namespace MambaAi\Tests\TestCase;

use PHPUnit\Framework\TestCase;

/**
 * Pattern Symfony : workspace temporaire réel par test, supprimé en tearDown().
 * Voir Symfony\Component\Filesystem\Tests\FilesystemTestCase.
 */
abstract class FilesystemTestCase extends TestCase
{
    protected string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'mamba_'.microtime(true).'.'.mt_rand();
        mkdir($this->workspace, 0o777, true);
        $this->workspace = realpath($this->workspace);
    }

    protected function tearDown(): void
    {
        if (isset($this->workspace) && is_dir($this->workspace)) {
            $this->rrmdir($this->workspace);
        }
    }

    /**
     * Écrit un arbre `agents/<name>/...` dans le workspace et retourne le chemin du dossier d'agent.
     *
     * @param array<string, string|array> $tree
     */
    protected function writeAgentTree(string $agentName, array $tree, ?string $root = null): string
    {
        $root ??= $this->workspace;
        $base = $root.\DIRECTORY_SEPARATOR.$agentName;
        if (!is_dir($base)) {
            mkdir($base, 0o755, true);
        }

        $this->writeTree($base, $tree);

        return $base;
    }

    /** @param array<string, string|array> $tree */
    private function writeTree(string $base, array $tree): void
    {
        foreach ($tree as $name => $value) {
            $path = $base.\DIRECTORY_SEPARATOR.$name;
            if (\is_array($value)) {
                if (!is_dir($path)) {
                    mkdir($path, 0o755, true);
                }
                $this->writeTree($path, $value);
            } else {
                file_put_contents($path, $value);
            }
        }
    }

    private function rrmdir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = $dir.\DIRECTORY_SEPARATOR.$entry;
            if (is_dir($path) && !is_link($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
