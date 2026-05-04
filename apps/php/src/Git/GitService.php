<?php

declare(strict_types=1);

namespace Cataloga\Git;

final class GitService
{
    public function __construct(private readonly string $repoRoot)
    {
    }

    public function statusShort(): array
    {
        return $this->runGitCommand(['status', '--short']);
    }

    public function diffRegistryAndCataloga(): array
    {
        return $this->runGitCommand(['diff', '--', 'registry']);
    }

    public function addRegistry(): array
    {
        return $this->runGitCommand(['add', 'registry']);
    }

    public function commit(string $message): array
    {
        return $this->runGitCommand(['commit', '-m', $message]);
    }

    public function revParseHead(): array
    {
        return $this->runGitCommand(['rev-parse', 'HEAD']);
    }

    private function runGitCommand(array $arguments): array
    {
        $command = array_merge(['git'], $arguments);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $this->repoRoot);
        if (!is_resource($process)) {
            return [
                'ok' => false,
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'Unable to execute git command.',
            ];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'ok' => $exitCode === 0,
            'exitCode' => $exitCode,
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
        ];
    }
}
