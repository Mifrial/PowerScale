<?php

declare(strict_types=1);

/**
 * Отвечает на вопросы по docs/tr/doc-index.json, не печатая весь индекс.
 *
 * Запуск: php tools/doc-index/show.php --module=Game [--state=ПЛАН] [--limit=20].
 * Файл не подпадает под phpcs: правила стандарта соблюдаются вручную.
 */

const STATE_ORDER = [
    'ПЛАН' => 0,
    'В РАБОТЕ' => 1,
    'ЧАСТИЧНО' => 2,
    'ОТМЕНЁН' => 3,
    'ОТЛОЖЕН' => 4,
    'СДЕЛАНО' => 5,
    'СНИМОК' => 6,
];

const FILTERS = [
    'module',
    'state',
    'zone',
    'kind',
    'basis',
    'limit',
    'no-status',
    'unrecognized',
    'broken',
    'title',
    'count',
    'json',
];

$options = parseArguments($argv);
if (isset($options['help']) || $options === []) {
    printUsage();
    exit(0);
}

$indexPath = dirname(__DIR__, 4) . '/docs/tr/doc-index.json';
if (!is_file($indexPath)) {
    fail('нет ' . $indexPath . ': сначала composer maps');
}

$decoded = json_decode((string) file_get_contents($indexPath), true);
$documents = is_array($decoded) ? ($decoded['documents'] ?? []) : [];
$matched = [];
foreach ((array) $documents as $document) {
    if (is_array($document) && matches($document, $options)) {
        $matched[] = $document;
    }
}

usort($matched, 'compareDocuments');
$limit = isset($options['limit']) ? (int) $options['limit'] : 20;
$shown = $limit > 0 ? array_slice($matched, 0, $limit) : $matched;

if (isset($options['json'])) {
    fwrite(STDOUT, renderJson($matched, $shown, $options));
    exit(0);
}

fwrite(STDOUT, renderText($matched, $shown, $options));
exit(0);

/**
 * Разбирает аргументы вида --name или --name=value.
 *
 * @param array<int, string> $argv Аргументы командной строки.
 *
 * @return array<string, string|bool> Опции.
 */
function parseArguments(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--help' || $argument === '-h') {
            $options['help'] = true;
            continue;
        }

        if (!str_starts_with($argument, '--')) {
            fail('неизвестный аргумент: ' . $argument);
        }

        $pair = explode('=', substr($argument, 2), 2);
        $name = $pair[0];
        if (!in_array($name, FILTERS, true)) {
            fail('неизвестный флаг: --' . $name . ' (см. --help)');
        }

        $options[$name] = $pair[1] ?? true;
    }

    return $options;
}

/**
 * Проверяет документ по всем фильтрам.
 *
 * @param array<string, mixed> $document Документ.
 * @param array<string, string|bool> $options Опции.
 *
 * @return bool Совпадение.
 */
function matches(array $document, array $options): bool
{
    $checks = [
        matchesModule($document, $options),
        matchesScalar($document, $options, 'state', 'state'),
        matchesScalar($document, $options, 'zone', 'zone'),
        matchesScalar($document, $options, 'kind', 'kind'),
        matchesBasis($document, $options),
        matchesFlags($document, $options),
    ];

    return !in_array(false, $checks, true);
}

/**
 * Проверяет фильтр по модулю: подстрока без учёта регистра.
 *
 * @param array<string, mixed> $document Документ.
 * @param array<string, string|bool> $options Опции.
 *
 * @return bool Совпадение.
 */
function matchesModule(array $document, array $options): bool
{
    if (!isset($options['module'])) {
        return true;
    }

    $needle = mb_strtolower((string) $options['module']);
    foreach ((array) ($document['modules'] ?? []) as $module) {
        if (str_contains(mb_strtolower((string) $module), $needle)) {
            return true;
        }
    }

    return false;
}

/**
 * Проверяет простое поле документа.
 *
 * @param array<string, mixed> $document Документ.
 * @param array<string, string|bool> $options Опции.
 * @param string $option Имя опции.
 * @param string $field Поле документа.
 *
 * @return bool Совпадение.
 */
function matchesScalar(array $document, array $options, string $option, string $field): bool
{
    if (!isset($options[$option])) {
        return true;
    }

    $expected = mb_strtoupper(trim((string) $options[$option], " \t~"));
    $actual = mb_strtoupper(trim((string) ($document[$field] ?? ''), " \t~"));

    return $expected === $actual;
}

/**
 * Проверяет фильтр по основанию.
 *
 * @param array<string, mixed> $document Документ.
 * @param array<string, string|bool> $options Опции.
 *
 * @return bool Совпадение.
 */
function matchesBasis(array $document, array $options): bool
{
    if (!isset($options['basis'])) {
        return true;
    }

    $expected = normalizeBasis((string) $options['basis']);
    $actual = normalizeBasis((string) ($document['basis'] ?? ''));

    return $expected === $actual;
}

/**
 * Проверяет булевы фильтры.
 *
 * @param array<string, mixed> $document Документ.
 * @param array<string, string|bool> $options Опции.
 *
 * @return bool Совпадение.
 */
function matchesFlags(array $document, array $options): bool
{
    $statusRaw = (string) ($document['statusRaw'] ?? '');
    $checks = [
        !isset($options['no-status']) || $statusRaw === '',
        !isset($options['unrecognized']) || ($document['state'] === null && $statusRaw !== ''),
        !isset($options['broken']) || (array) ($document['brokenLinks'] ?? []) !== [],
    ];

    return !in_array(false, $checks, true);
}

/**
 * Приводит основание к единому виду.
 *
 * @param string $value Значение.
 *
 * @return string Нормализованное значение.
 */
function normalizeBasis(string $value): string
{
    return mb_strtoupper(str_replace(['-', '_'], ' ', trim($value, " \t~")));
}

/**
 * Сортирует документы: сначала открытые, затем по пути.
 *
 * @param array<string, mixed> $left Документ.
 * @param array<string, mixed> $right Документ.
 *
 * @return int Результат сравнения.
 */
function compareDocuments(array $left, array $right): int
{
    $leftRank = STATE_ORDER[$left['state'] ?? ''] ?? 9;
    $rightRank = STATE_ORDER[$right['state'] ?? ''] ?? 9;
    if ($leftRank !== $rightRank) {
        return $leftRank <=> $rightRank;
    }

    return (string) $left['path'] <=> (string) $right['path'];
}

/**
 * Рендерит текстовый ответ.
 *
 * @param array<int, array<string, mixed>> $matched Все совпадения.
 * @param array<int, array<string, mixed>> $shown Показанная часть.
 * @param array<string, string|bool> $options Опции.
 *
 * @return string Текст.
 */
function renderText(array $matched, array $shown, array $options): string
{
    if (isset($options['count'])) {
        return sprintf('%s · %d', describeFilters($options), count($matched)) . PHP_EOL;
    }

    $lines = [sprintf('%s · %d из %d', describeFilters($options), count($shown), count($matched))];
    foreach ($shown as $document) {
        $lines[] = renderLine($document, isset($options['title']));
    }

    if ($matched === []) {
        $lines[] = 'нет совпадений';
    } elseif (count($matched) > count($shown)) {
        $lines[] = sprintf('… ещё %d; --limit=0 — все', count($matched) - count($shown));
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

/**
 * Рендерит одну строку результата.
 *
 * @param array<string, mixed> $document Документ.
 * @param bool $withTitle Показывать заголовок.
 *
 * @return string Строка.
 */
function renderLine(array $document, bool $withTitle): string
{
    $statusRaw = (string) ($document['statusRaw'] ?? '');
    $state = '—';
    if ($document['state'] !== null) {
        $state = '~' . $document['state'] . '~';
    } elseif ($statusRaw !== '') {
        $state = '?';
    }

    $line = sprintf('%s  %s  %s', $state, $document['date'] ?? '—', $document['path']);
    if ($withTitle && (string) ($document['title'] ?? '') !== '') {
        $line .= '  ' . $document['title'];
    }

    $broken = (array) ($document['brokenLinks'] ?? []);
    if ($broken !== []) {
        $line .= '  -> ' . implode(', ', $broken);
    }

    return $line;
}

/**
 * Описывает активные фильтры.
 *
 * @param array<string, string|bool> $options Опции.
 *
 * @return string Описание.
 */
function describeFilters(array $options): string
{
    $parts = [];
    foreach (['module', 'state', 'zone', 'kind', 'basis'] as $name) {
        if (isset($options[$name])) {
            $parts[] = $name . '=' . (string) $options[$name];
        }
    }

    foreach (['no-status', 'unrecognized', 'broken'] as $name) {
        if (isset($options[$name])) {
            $parts[] = '--' . $name;
        }
    }

    return $parts === [] ? 'все документы' : implode(', ', $parts);
}

/**
 * Рендерит JSON-ответ.
 *
 * @param array<int, array<string, mixed>> $matched Все совпадения.
 * @param array<int, array<string, mixed>> $shown Показанная часть.
 * @param array<string, string|bool> $options Опции.
 *
 * @return string JSON.
 */
function renderJson(array $matched, array $shown, array $options): string
{
    $payload = [
        'filters' => describeFilters($options),
        'total' => count($matched),
        'shown' => count($shown),
        'documents' => array_values($shown),
    ];

    return (string) json_encode(
        $payload,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    ) . PHP_EOL;
}

/**
 * Печатает подсказку.
 *
 * @return void
 */
function printUsage(): void
{
    $usage = <<<'TEXT'
    Запросы к карте документации (docs/tr/doc-index.json).

    Использование:
      php www/mifrial/tools/doc-index/show.php --module=Game --zone=tr
      php www/mifrial/tools/doc-index/show.php --module=Roleplay/Rule --zone=tr --state=ПЛАН
      php www/mifrial/tools/doc-index/show.php --zone=tr --kind=plan --no-status
      php www/mifrial/tools/doc-index/show.php --broken --limit=0

    Фильтры (комбинируются):
      --module=Game        подстрока ключа модуля без учёта регистра;
                           без --zone выдача включает review/specs/снимки
      --state=ПЛАН         состояние: ПЛАН, В РАБОТЕ, ЧАСТИЧНО, СДЕЛАНО,
                           ОТЛОЖЕН, ОТМЕНЁН, СНИМОК (тильды не нужны)
      --zone=tr            зона: tr, review, specs, rule, tz, old, promts
      --kind=plan          тип: plan, canon, snapshot, spec, rule, history, doc
      --basis=НЕ-ОСНОВА    основание: PHP, МОК, НЕ ОСНОВА
      --no-status          нет строки **Статус:**
      --unrecognized       статус объявлен прозой, но не распознан
      --broken             есть битые ссылки
      --limit=20           сколько строк показать; 0 — все
      --title              добавить заголовок документа
      --count              только счётчик
      --json               машиночитаемый вывод
    TEXT;
    fwrite(STDOUT, $usage . PHP_EOL);
}

/**
 * Завершает работу с сообщением об ошибке.
 *
 * @param string $message Сообщение.
 *
 * @return never
 */
function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}
