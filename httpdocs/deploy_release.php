<?php

declare(strict_types=1);

header('Content-Type: application/json');

$projectRoot = dirname(__DIR__);
$envFile = $projectRoot.'/.env.local';
$archivePath = $projectRoot.'/deploy-package.zip';
$tempDir = $projectRoot.'/var/deploy-extract';
$suppliedSecret = $_GET['secret'] ?? $_POST['secret'] ?? '';

$envValues = [];
if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            if ((str_starts_with($value, "'") && str_ends_with($value, "'")) || (str_starts_with($value, '"') && str_ends_with($value, '"'))) {
                $value = substr($value, 1, -1);
            }
            $envValues[trim($key)] = str_replace("'\"'\"'", "'", $value);
        }
    }
}

$expectedSecret = $envValues['WEBHOOK_SECRET'] ?? '';
if ('' === $expectedSecret || !hash_equals($expectedSecret, (string) $suppliedSecret)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
    ], JSON_PRETTY_PRINT);

    return;
}

if (!class_exists(ZipArchive::class)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'ZipArchive extension is not available.',
    ], JSON_PRETTY_PRINT);

    return;
}

if (!is_file($archivePath)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'deploy-package.zip not found.',
    ], JSON_PRETTY_PRINT);

    return;
}

try {
    removeDirectory($tempDir);
    if (!is_dir(dirname($tempDir))) {
        mkdir(dirname($tempDir), 0775, true);
    }
    mkdir($tempDir, 0775, true);

    $zip = new ZipArchive();
    if (true !== $zip->open($archivePath)) {
        throw new RuntimeException('Unable to open deployment archive.');
    }

    if (!$zip->extractTo($tempDir)) {
        $zip->close();
        throw new RuntimeException('Unable to extract deployment archive.');
    }

    $zip->close();

    copyDirectoryContents($tempDir, $projectRoot);
    @unlink($archivePath);
    removeDirectory($tempDir);

    $results = [];
    $commands = [
        'cache:clear' => ['cache:clear', '--env=prod', '--no-warmup'],
        'cache:warmup' => ['cache:warmup', '--env=prod'],
        'doctrine:migrations:migrate' => ['doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration'],
    ];

    foreach ($commands as $label => $arguments) {
        $result = runConsoleCommand($projectRoot, $arguments);
        $results[$label] = $result['output'];

        if (0 !== $result['exitCode']) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'step' => $label,
                'output' => $results,
            ], JSON_PRETTY_PRINT);

            return;
        }
    }

    echo json_encode([
        'success' => true,
        'output' => $results,
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    removeDirectory($tempDir);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
}

function runConsoleCommand(string $projectRoot, array $arguments): array
{
    $command = array_merge([PHP_BINARY, $projectRoot.'/bin/console'], $arguments);
    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, $projectRoot, [
        'APP_ENV' => 'prod',
        'APP_DEBUG' => '0',
    ]);

    if (!is_resource($process)) {
        return [
            'exitCode' => 1,
            'output' => 'Unable to start console process.',
        ];
    }

    $stdout = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exitCode' => $exitCode,
        'output' => trim($stdout."\n".$stderr),
    ];
}

function copyDirectoryContents(string $source, string $destination): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $targetPath = $destination.'/'.substr($item->getPathname(), strlen($source) + 1);

        if ($item->isDir()) {
            if (!is_dir($targetPath) && !mkdir($targetPath, 0775, true) && !is_dir($targetPath)) {
                throw new RuntimeException(sprintf('Unable to create directory "%s".', $targetPath));
            }

            continue;
        }

        $targetDirectory = dirname($targetPath);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException(sprintf('Unable to create directory "%s".', $targetDirectory));
        }

        if (!copy($item->getPathname(), $targetPath)) {
            throw new RuntimeException(sprintf('Unable to copy file "%s".', $targetPath));
        }
    }
}

function removeDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($path);
}
