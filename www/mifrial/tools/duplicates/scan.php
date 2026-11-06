<?php

declare(strict_types=1);

/**
 * Отчёт по дублям в PHP-коде модулей и гейт на composer quality.
 *
 * Запуск:
 *   composer dup                 отчёт (exit 0)
 *   composer dup:check           гейт по потолку (exit 1 при превышении)
 *   composer dup:ratchet         понизить потолок до текущего значения
 *
 * Опции:
 *   --root=<dir>      каталог сканирования (по умолчанию modules)
 *   --config=<file>   конфиг (по умолчанию tools/duplicates/config.json)
 *   --module=<name>   только кластеры модуля (Game или Roleplay/Game)
 *   --top=<n>         сколько кластеров печатать (0 — все)
 *   --json            машинный вывод
 *
 * Файл не подпадает под phpcs: правила стандарта соблюдаются вручную.
 */

use Mifrial\Tools\Duplicates\Cluster;
use Mifrial\Tools\Duplicates\Scanner;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$baseDir = realpath(dirname(__DIR__, 2));
if ($baseDir === false) {
    fwrite(STDERR, 'cannot resolve project root' . PHP_EOL);
    exit(2);
}

$options = parseArguments($argv);
if (isset($options['help'])) {
    printUsage();
    exit(0);
}

$configFile = isset($options['config'])
    ? resolvePath((string) $options['config'], $baseDir)
    : $baseDir . '/tools/duplicates/config.json';

$config = readConfig($configFile);
$root = isset($options['root'])
    ? resolvePath((string) $options['root'], $baseDir)
    : $baseDir . '/modules';

$onlyModule = isset($options['module']) ? (string) $options['module'] : null;
if ($onlyModule !== null && isset($options['check'])) {
    fwrite(STDERR, '--check works on the whole project, not a single module' . PHP_EOL);
    exit(2);
}

if ($onlyModule !== null) {
    $config['onlyModule'] = $onlyModule;
}

$scanner = Scanner::fromConfig($root, $config);
$result = $scanner->scan();
$stats = $result['stats'];
$clusters = $result['clusters'];

$ceiling = (int) ($config['gate']['maxDuplicatedLines'] ?? 0);

if (isset($options['ratchet'])) {
    $updated = ratchet($configFile, $config, (int) $stats['gateLines'], (int) $stats['gateClusters']);
    exit($updated ? 0 : 1);
}

$scoped = $onlyModule !== null;

if (isset($options['json'])) {
    fwrite(STDOUT, renderJson($stats, $clusters, $ceiling, $scoped) . PHP_EOL);

    exit(gateExitCode($stats, $ceiling, isset($options['check'])));
}

$top = isset($options['top']) ? max(0, (int) $options['top']) : 10;
fwrite(STDOUT, renderText($stats, $clusters, $ceiling, $top, isset($options['check']), $scoped));

exit(gateExitCode($stats, $ceiling, isset($options['check'])));

/**
 * @param list<string> $arguments
 *
 * @return array<string, string|true>
 */
function parseArguments(array $arguments): array
{
    $options = [];

    foreach (array_slice($arguments, 1) as $argument) {
        if (!str_starts_with($argument, '--')) {
            continue;
        }

        $body = substr($argument, 2);
        $position = strpos($body, '=');
        if ($position === false) {
            $options[$body] = true;
            continue;
        }

        $options[substr($body, 0, $position)] = substr($body, $position + 1);
    }

    return $options;
}

function printUsage(): void
{
    $usage = <<<'TEXT'
    Usage: php tools/duplicates/scan.php [options]

      --root=<dir>      directory to scan (default: modules)
      --config=<file>   config file (default: tools/duplicates/config.json)
      --module=<name>   only clusters of one module (Game or Roleplay/Game)
      --top=<n>         clusters to print, 0 for all (default: 10)
      --json            machine-readable output
      --check           exit 1 when duplicated lines exceed the ceiling
      --ratchet         lower the ceiling to the current value
      --help            this message

    TEXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

function resolvePath(string $path, string $baseDir): string
{
    if (str_starts_with($path, '/')) {
        return $path;
    }

    return $baseDir . '/' . $path;
}

/**
 * @return array<string, mixed>
 */
function readConfig(string $configFile): array
{
    if (!is_file($configFile)) {
        fwrite(STDERR, sprintf('config not found: %s', $configFile) . PHP_EOL);
        exit(2);
    }

    $decoded = json_decode((string) file_get_contents($configFile), true);
    if (!is_array($decoded)) {
        fwrite(STDERR, sprintf('config is not valid JSON: %s', $configFile) . PHP_EOL);
        exit(2);
    }

    return $decoded;
}

/**
 * @param array<string, mixed> $config
 */
function ratchet(string $configFile, array $config, int $gateLines, int $gateClusters): bool
{
    $current = (int) ($config['gate']['maxDuplicatedLines'] ?? 0);

    if ($gateLines > $current) {
        fwrite(STDERR, sprintf(
            'ceiling cannot be raised: current %d > ceiling %d',
            $gateLines,
            $current,
        ) . PHP_EOL);

        return false;
    }

    if ($gateLines === $current) {
        fwrite(STDOUT, sprintf('ceiling is already at %d lines (%d clusters)', $current, $gateClusters) . PHP_EOL);

        return true;
    }

    $config['gate']['maxDuplicatedLines'] = $gateLines;
    $encoded = json_encode(
        $config,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    );
    file_put_contents($configFile, $encoded . PHP_EOL);

    fwrite(STDOUT, sprintf('ceiling lowered: %d -> %d lines (%d clusters)', $current, $gateLines, $gateClusters) . PHP_EOL);

    return true;
}

/**
 * @param array<string, mixed> $stats
 */
function gateExitCode(array $stats, int $ceiling, bool $check): int
{
    if (!$check) {
        return 0;
    }

    return (int) $stats['gateLines'] > $ceiling ? 1 : 0;
}

/**
 * @param array<string, mixed> $stats
 * @param list<Cluster> $clusters
 */
function renderText(array $stats, array $clusters, int $ceiling, int $top, bool $check, bool $scoped): string
{
    $kinds = $stats['kinds'];
    $codeLines = max(1, (int) $stats['codeLines']);
    $percent = static fn(int $lines): string => number_format(100 * $lines / $codeLines, 2) . '%';

    $lines = [];
    $lines[] = sprintf(
        'duplicates: %d clusters, %d lines / %d code lines (%s)',
        $stats['clusters'],
        $stats['lines'],
        $stats['codeLines'],
        $percent((int) $stats['lines']),
    );
    $lines[] = $scoped
        ? sprintf(
            'gate:       %d clusters, %d lines (%s) | module scope, ceiling not applied',
            $stats['gateClusters'],
            $stats['gateLines'],
            $percent((int) $stats['gateLines']),
        )
        : sprintf(
            'gate:       %d clusters, %d lines (%s) | ceiling %d | %s',
            $stats['gateClusters'],
            $stats['gateLines'],
            $percent((int) $stats['gateLines']),
            $ceiling,
            (int) $stats['gateLines'] > $ceiling ? 'EXCEEDED' : 'OK',
        );
    $lines[] = sprintf(
        'info:       boilerplate %d, cross-module %d, allowed %d',
        $kinds[Cluster::KIND_BOILERPLATE],
        $kinds[Cluster::KIND_CROSS_MODULE],
        $kinds[Cluster::KIND_ALLOWED],
    );

    if ($stats['byModule'] !== []) {
        $lines[] = '';
        $lines[] = 'gate by module:';
        foreach (array_slice($stats['byModule'], 0, 10, true) as $module => $count) {
            $lines[] = sprintf('  %6d  %s', $count, $module);
        }
    }

    $gateClusters = array_values(array_filter(
        $clusters,
        static fn(Cluster $cluster): bool => $cluster->kind === Cluster::KIND_GATE,
    ));
    usort($gateClusters, static fn(Cluster $left, Cluster $right): int => $right->lines <=> $left->lines);

    $shown = $top === 0 ? $gateClusters : array_slice($gateClusters, 0, $top);
    if ($shown !== []) {
        $lines[] = '';
        $lines[] = sprintf('top gate clusters (%d of %d):', count($shown), count($gateClusters));
        foreach ($shown as $cluster) {
            $lines[] = sprintf('  %4d lines, %d copies', $cluster->lines, count($cluster->occurrences));
            foreach ($cluster->occurrences as $occurrence) {
                $lines[] = sprintf(
                    '        %s:%d-%d',
                    $occurrence->path,
                    $occurrence->line,
                    $occurrence->endLine,
                );
            }
        }
    }

    if ($check && (int) $stats['gateLines'] > $ceiling) {
        $lines[] = '';
        $lines[] = sprintf('FAIL: duplicated lines exceed the ceiling by %d', (int) $stats['gateLines'] - $ceiling);
        $lines[] = 'Fix a cluster or add a justified allow rule to tools/duplicates/config.json.';
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

/**
 * @param array<string, mixed> $stats
 * @param list<Cluster> $clusters
 */
function renderJson(array $stats, array $clusters, int $ceiling, bool $scoped): string
{
    return json_encode(
        [
            'stats' => $stats,
            'ceiling' => $scoped ? null : $ceiling,
            'exceeded' => $scoped ? null : (int) $stats['gateLines'] > $ceiling,
            'clusters' => array_map(static fn(Cluster $cluster): array => $cluster->toArray(), $clusters),
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    );
}
