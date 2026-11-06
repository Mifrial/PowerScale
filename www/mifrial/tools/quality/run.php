<?php

declare(strict_types=1);

/**
 * Гоняет все полосы `composer quality` и не прячет результат следующей
 * полосы за падением предыдущей.
 *
 * Запуск: composer quality (или php tools/quality/run.php).
 * Файл не подпадает под phpcs: правила стандарта соблюдаются вручную.
 */

const QUALITY_STEPS = [
    'preflight' => ['tools/phpcs/preflight.php'],
    'phpcs' => ['vendor/bin/phpcs', '--standard=phpcs-quality.xml.dist'],
    'duplicates' => ['tools/duplicates/scan.php', '--check', '--top=5'],
];

$root = dirname(__DIR__, 2);
chdir($root);

$failed = [];
foreach (QUALITY_STEPS as $name => $arguments) {
    fwrite(STDOUT, sprintf('== %s ==%s', $name, PHP_EOL));

    $command = implode(' ', array_map(
        static fn(string $argument): string => escapeshellarg($argument),
        array_merge([PHP_BINARY], $arguments),
    ));

    $exitCode = 0;
    passthru($command, $exitCode);

    if ($exitCode !== 0) {
        $failed[] = $name;
    }
}

fwrite(STDOUT, PHP_EOL . 'quality: ');
if ($failed === []) {
    fwrite(STDOUT, 'all bands passed' . PHP_EOL);
    exit(0);
}

fwrite(STDOUT, sprintf('failed bands: %s%s', implode(', ', $failed), PHP_EOL));
exit(1);
