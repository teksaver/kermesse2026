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

$maxLines = 120;
$logCandidates = [
    'symfony_prod' => $projectRoot.'/var/log/prod.log',
    'symfony_dev' => $projectRoot.'/var/log/dev.log',
    'php_error_log_project' => $projectRoot.'/error_log',
    'php_error_log_httpdocs' => $projectRoot.'/httpdocs/error_log',
];

$logs = [];
foreach ($logCandidates as $name => $path) {
    $logs[$name] = [
        'path' => $path,
        'exists' => is_file($path),
        'tail' => is_file($path) ? tailFile($path, $maxLines) : null,
    ];
}

echo json_encode([
    'success' => true,
    'php_version' => PHP_VERSION,
    'project_root' => $projectRoot,
    'logs' => $logs,
], JSON_PRETTY_PRINT);

function tailFile(string $path, int $lines): string
{
    $content = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($content)) {
        return '';
    }

    return implode("\n", array_slice($content, -$lines));
}

