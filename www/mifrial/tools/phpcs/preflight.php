<?php

declare(strict_types=1);

/**
 * Проверяет готовность проектных инструментов PHP-проверки.
 */

$projectRoot = dirname(__DIR__, 2);
$phpcsPath = $projectRoot . '/vendor/bin/phpcs';
$phpCsFixerPath = $projectRoot . '/vendor/bin/php-cs-fixer';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

if (PHP_VERSION_ID < 80200 || PHP_VERSION_ID >= 90000) {
    $fail('PHP version ^8.2 is required.');
}

foreach ([$phpcsPath, $phpCsFixerPath] as $toolPath) {
    if (!is_file($toolPath) || !is_executable($toolPath)) {
        $fail(
            'Project PHP tools are not installed. '
            . 'Run composer install in www/mifrial.'
        );
    }
}

$process = proc_open(
    [$phpcsPath, '-i'],
    [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ],
    $pipes,
    $projectRoot
);

if (!is_resource($process)) {
    $fail('Unable to execute project PHPCS.');
}

$installedStandards = stream_get_contents($pipes[1]);
$processError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if ($exitCode !== 0) {
    $fail('Project PHPCS preflight failed: ' . trim($processError));
}

if (!str_contains($installedStandards, 'SlevomatCodingStandard')) {
    $fail(
        'SlevomatCodingStandard is not installed. '
        . 'Run composer install in www/mifrial.'
    );
}
