<?php

declare(strict_types=1);

$repoRoot = trim((string) shell_exec('git rev-parse --show-toplevel 2>/dev/null'));

if ($repoRoot === '') {
    fwrite(STDERR, "Unable to determine the git repository root.\n");
    exit(1);
}

$backendRoot = realpath(__DIR__.'/..');

if ($backendRoot === false) {
    fwrite(STDERR, "Unable to determine the backend root.\n");
    exit(1);
}

$backendPrefix = trim(str_replace('\\', '/', substr($backendRoot, strlen($repoRoot))), '/');
$stagedFiles = [];

exec(sprintf('git -C %s diff --cached --name-only --diff-filter=ACM', escapeshellarg($repoRoot)), $stagedFiles, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "Unable to read staged files from git.\n");
    exit($exitCode);
}

$phpFiles = array_values(array_filter(array_map(
    static function (string $file) use ($backendPrefix, $backendRoot): ?string {
        $normalizedFile = str_replace('\\', '/', trim($file));

        if (! str_starts_with($normalizedFile, $backendPrefix.'/') || ! str_ends_with($normalizedFile, '.php')) {
            return null;
        }

        $relativePath = substr($normalizedFile, strlen($backendPrefix) + 1);
        $absolutePath = $backendRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        return is_file($absolutePath) ? $relativePath : null;
    },
    $stagedFiles,
)));

if ($phpFiles === []) {
    fwrite(STDOUT, "No staged backend PHP files to check with Pint.\n");
    exit(0);
}

$escapedFiles = implode(' ', array_map(static fn (string $file): string => escapeshellarg($file), $phpFiles));
$command = sprintf('cd %s && ./vendor/bin/pint --test %s', escapeshellarg($backendRoot), $escapedFiles);

passthru($command, $pintExitCode);
exit($pintExitCode);
