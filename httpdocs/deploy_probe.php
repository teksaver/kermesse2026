<?php

declare(strict_types=1);

header('Content-Type: application/json');

$projectRoot = dirname(__DIR__);
$envFile = $projectRoot.'/.env.local';
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

$response = [
    'success' => true,
    'php_version' => PHP_VERSION,
    'project_root' => $projectRoot,
    'files' => [
        '.env.local' => is_file($envFile),
        'bin/console' => is_file($projectRoot.'/bin/console'),
        'vendor/autoload_runtime.php' => is_file($projectRoot.'/vendor/autoload_runtime.php'),
        'src/Controller/WebhookController.php' => is_file($projectRoot.'/src/Controller/WebhookController.php'),
        'httpdocs/index.php' => is_file(__DIR__.'/index.php'),
    ],
    'writable' => [
        'project_root' => is_writable($projectRoot),
        'var_dir' => is_dir($projectRoot.'/var') ? is_writable($projectRoot.'/var') : null,
    ],
    'extensions' => [
        'zip' => class_exists(ZipArchive::class),
    ],
];

echo json_encode($response, JSON_PRETTY_PRINT);
