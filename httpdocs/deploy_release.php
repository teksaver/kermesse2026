<?php

declare(strict_types=1);

header('Content-Type: application/json');

$projectRoot = dirname(__DIR__);
$envFile = $projectRoot.'/.env.local';
$archiveCandidates = [
    $projectRoot.'/deploy-package.zip',
    $projectRoot.'/httpdocs/deploy-package.zip',
];
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

if (isset($_GET['action']) && 'list' === $_GET['action']) {
    header('Content-Type: text/plain');
    $files = scandir($projectRoot);
    foreach ($files as $file) {
        $path = $projectRoot . '/' . $file;
        echo $file . (is_dir($path) ? '/' : (' (' . filesize($path) . ' bytes)')) . "\n";
    }
    return;
}

if (isset($_GET['action']) && 'logs' === $_GET['action']) {
    header('Content-Type: text/plain');
    $logFile = $projectRoot.'/var/log/prod.log';
    if (!is_file($logFile)) {
        echo "Log file not found.";
        return;
    }

    $fileSize = filesize($logFile);
    if ($fileSize > 2 * 1024 * 1024) { // Read only last 2MB if huge
        $fp = fopen($logFile, 'r');
        fseek($fp, -2 * 1024 * 1024, SEEK_END);
        echo fread($fp, 2 * 1024 * 1024);
        fclose($fp);
    } else {
        echo file_get_contents($logFile);
    }
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

[$archivePath, $archiveFound] = resolveArchivePath($archiveCandidates);
if (!$archiveFound) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'deploy-package.zip not found.',
        'checked_paths' => $archiveCandidates,
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
        'cache:clear' => [
            'command' => 'cache:clear',
            '--env' => 'prod',
            '--no-warmup' => true,
        ],
        'cache:warmup' => [
            'command' => 'cache:warmup',
            '--env' => 'prod',
        ],
        'doctrine:migrations:migrate' => [
            'command' => 'doctrine:migrations:migrate',
            '--no-interaction' => true,
            '--allow-no-migration' => true,
        ],
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

function runConsoleCommand(string $projectRoot, array $inputArguments): array
{
    static $bootstrapped = false;
    if (!$bootstrapped) {
        require_once $projectRoot.'/vendor/autoload.php';

        if (class_exists(\Symfony\Component\Dotenv\Dotenv::class)) {
            (new \Symfony\Component\Dotenv\Dotenv())->bootEnv($projectRoot.'/.env');
        }

        $bootstrapped = true;
    }

    if (!class_exists(\App\Kernel::class)) {
        return [
            'exitCode' => 1,
            'output' => 'App\\Kernel class not found after autoload.',
        ];
    }

    $kernel = new \App\Kernel('prod', false);
    $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
    $application->setAutoExit(false);
    $input = new \Symfony\Component\Console\Input\ArrayInput($inputArguments);
    $output = new \Symfony\Component\Console\Output\BufferedOutput();

    try {
        $exitCode = $application->run($input, $output);
    } catch (Throwable $e) {
        $kernel->shutdown();

        return [
            'exitCode' => 1,
            'output' => trim($output->fetch()."\n".$e->getMessage()),
        ];
    }

    $kernel->shutdown();

    return [
        'exitCode' => $exitCode,
        'output' => trim($output->fetch()),
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

/**
 * @param list<string> $candidates
 *
 * @return array{0:string,1:bool}
 */
function resolveArchivePath(array $candidates): array
{
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return [$candidate, true];
        }
    }

    return [$candidates[0] ?? '', false];
}
