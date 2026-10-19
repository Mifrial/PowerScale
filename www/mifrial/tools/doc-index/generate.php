<?php

declare(strict_types=1);

/**
 * Собирает карту документации docs/: тип, статус, слои, связи и битые ссылки.
 *
 * Запуск: composer docs-map (запись) или composer docs-check (сверка).
 * Файл не подпадает под phpcs: правила стандарта соблюдаются вручную.
 */

const STATE_TOKENS = ['ПЛАН', 'В РАБОТЕ', 'ЧАСТИЧНО', 'СДЕЛАНО', 'ОТЛОЖЕН', 'ОТМЕНЁН', 'СНИМОК'];
const LAYER_TOKENS = ['PHP', 'VUE', 'МОК', 'НИГДЕ'];
const BASIS_TOKENS = ['PHP', 'МОК', 'НЕ ОСНОВА'];

const KIND_LABELS = [
    'canon' => 'канон',
    'plan' => 'план',
    'snapshot' => 'снимок',
    'spec' => 'спека',
    'rule' => 'правила',
    'history' => 'история',
    'doc' => 'док',
];

const MODULE_SLUGS = [
    'smarttable' => 'Core/SmartTable',
    'rulespace' => 'Roleplay/RuleSpace',
    'versioning' => 'Versioning/Space',
    'mechanic' => 'Roleplay/Mechanic',
    'character' => 'Roleplay/Character',
    'keyword' => 'Roleplay/Keyword',
    'game' => 'Roleplay/Game',
    'rule' => 'Roleplay/Rule',
    'spell' => 'Roleplay/Rule',
    'chat' => 'Messages/Chat',
    'notifications' => 'Messages/Notifications',
    'user' => 'Core/User',
    'auth' => 'Core/Auth',
    'logger' => 'Core/Logger',
    'kernel' => 'Core/Kernel',
    'agent' => 'Core/Agent',
    'mail' => 'Core/Mail',
    'engine' => 'Core/Engine',
    'ui' => 'Core/UI',
    'home' => 'Roleplay/Home',
];

$toolRoot = dirname(__DIR__, 2);
$repoRoot = dirname($toolRoot, 2);
$docsRoot = $repoRoot . '/docs';
$indexDir = $docsRoot . '/tr';
$checkOnly = in_array('--check', $argv, true);

$allowlist = readAllowlist(__DIR__ . '/allowlist.json');
$documents = collectDocuments($docsRoot, $repoRoot);
$documents = attachBacklinks($documents);
$summary = summarize($documents);

$markdown = renderMarkdown($documents, $summary, $allowlist);
$json = renderJson($documents, $summary) . PHP_EOL;

if ($checkOnly) {
    failUnlessSame($indexDir . '/doc-index.md', $markdown, 'doc-index.md');
    failUnlessSame($indexDir . '/doc-index.json', $json, 'doc-index.json');
    checkBrokenLinks($documents, $allowlist);
    checkPlanStatuses($documents);
    checkTrustMap($repoRoot);
    fwrite(STDOUT, 'doc index is up to date' . PHP_EOL);
    exit(0);
}

writeFile($indexDir . '/doc-index.md', $markdown);
writeFile($indexDir . '/doc-index.json', $json);
fwrite(STDOUT, sprintf(
    'doc index written: %d documents, %d broken links%s',
    count($documents),
    $summary['brokenLinks'],
    PHP_EOL,
));
exit(0);

/**
 * Читает список исключений для ссылок.
 *
 * @param string $path Путь к allowlist.json.
 *
 * @return array<string, string> Ключ "документ|цель" -> причина.
 */
function readAllowlist(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    $entries = is_array($decoded) ? ($decoded['links'] ?? []) : [];
    $allowlist = [];
    foreach ((array) $entries as $entry) {
        if (!is_array($entry) || !isset($entry['path'], $entry['target'])) {
            continue;
        }

        $key = (string) $entry['path'] . '|' . (string) $entry['target'];
        $allowlist[$key] = (string) ($entry['reason'] ?? '');
    }

    return $allowlist;
}

/**
 * Обходит docs и разбирает каждый markdown-документ.
 *
 * @param string $docsRoot Каталог docs.
 * @param string $repoRoot Корень репозитория.
 *
 * @return array<string, array<string, mixed>> Документы по пути.
 */
function collectDocuments(string $docsRoot, string $repoRoot): array
{
    $documents = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($docsRoot, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'md') {
            continue;
        }

        $path = $file->getPathname();
        if (str_contains($path, '/node_modules/')) {
            continue;
        }

        $relative = ltrim(str_replace($repoRoot, '', $path), '/');
        if (isGeneratedArtifact($relative)) {
            continue;
        }

        $documents[$relative] = parseDocument($path, $relative, $repoRoot);
    }

    ksort($documents);

    return $documents;
}

/**
 * Проверяет, что файл сгенерирован инструментами.
 *
 * @param string $relative Путь документа от корня репозитория.
 *
 * @return bool Признак сгенерированного артефакта.
 */
function isGeneratedArtifact(string $relative): bool
{
    if ($relative === 'docs/tr/doc-index.md' || $relative === 'docs/tr/module-map.md') {
        return true;
    }

    return str_starts_with($relative, 'docs/tr/module-map/');
}

/**
 * Разбирает один документ: шапку, тип, ссылки.
 *
 * @param string $path Абсолютный путь.
 * @param string $relative Путь от корня репозитория.
 * @param string $repoRoot Корень репозитория.
 *
 * @return array<string, mixed> Описание документа.
 */
function parseDocument(string $path, string $relative, string $repoRoot): array
{
    $contents = (string) file_get_contents($path);
    $header = readHeader($contents);
    $status = statusFromHeader($header);
    $kind = documentKind($relative);
    $state = $status['state'];
    $source = $status['source'];
    if ($state === null && $kind === 'snapshot') {
        $state = 'СНИМОК';
        $source = 'role';
    }

    $links = extractLinks($contents, $relative, $repoRoot);
    $broken = 0;
    foreach ($links as $link) {
        if ($link['broken'] === true) {
            $broken++;
        }
    }

    return [
        'path' => $relative,
        'title' => titleOf($contents),
        'zone' => documentZone($relative),
        'kind' => $kind,
        'state' => $state,
        'stateSource' => $source,
        'statusRaw' => $status['raw'],
        'layers' => $status['layers'],
        'basis' => $status['basis'],
        'date' => $status['date'],
        'bytes' => strlen($contents),
        'lines' => substr_count($contents, "\n") + 1,
        'modules' => modulesFromPath($relative),
        'links' => $links,
        'broken' => $broken,
        'backlinks' => [],
    ];
}

/**
 * Возвращает первые строки документа: там объявлен статус.
 *
 * @param string $contents Содержимое файла.
 *
 * @return string Шапка документа.
 */
function readHeader(string $contents): string
{
    $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

    return implode("\n", array_slice($lines, 0, 14));
}

/**
 * Читает состояние, слои и основание из шапки документа.
 *
 * @param string $header Шапка документа.
 *
 * @return array{state: string|null, layers: array<int, string>, basis: string|null, date: string|null, source: string}
 *         Статус документа.
 */
function statusFromHeader(string $header): array
{
    $status = [
        'state' => null,
        'layers' => [],
        'basis' => null,
        'date' => null,
        'source' => 'none',
        'raw' => '',
    ];
    foreach (preg_split('/\r\n|\r|\n/', $header) ?: [] as $line) {
        if (preg_match('/^\s*\*\*Статус:\*\*\s*(.*)$/u', $line, $match) === 1) {
            $status['raw'] = trim($match[1]);
            $status = statusFromLine(trim($match[1]), $status);
            continue;
        }

        if (preg_match('/^\s*\*\*Реализация:\*\*\s*(.*)$/u', $line, $match) === 1) {
            $status['layers'] = layersOf(trim($match[1]));
            continue;
        }

        if (preg_match('/^\s*\*\*Опираться на:\*\*\s*(.*)$/u', $line, $match) === 1) {
            $status['basis'] = tokenOf(trim($match[1]), BASIS_TOKENS);
        }
    }

    return $status;
}

/**
 * Разбирает строку статуса: токен или проза.
 *
 * @param string $text Текст строки без префикса.
 * @param array{state: string|null, layers: array<int, string>, basis: string|null, date: string|null, source: string} $status
 *        Текущий статус.
 *
 * @return array{state: string|null, layers: array<int, string>, basis: string|null, date: string|null, source: string}
 *         Обновлённый статус.
 */
function statusFromLine(string $text, array $status): array
{
    $status['date'] = extractDate($text);
    $token = tokenOf($text, STATE_TOKENS);
    if ($token !== null) {
        $status['state'] = $token;
        $status['source'] = 'token';
    } else {
        $status['state'] = stateFromProse($text);
        $status['source'] = $status['state'] === null ? 'none' : 'prose';
    }

    $status['layers'] = layersOf($text);

    return $status;
}

/**
 * Возвращает состояние по прозаической строке статуса.
 *
 * @param string $text Текст строки статуса.
 *
 * @return string|null Токен состояния или null.
 */
function stateFromProse(string $text): ?string
{
    $lower = mb_strtolower($text);
    if (preg_match('/каркас|частично|ядро php|ядро бэк/u', $lower) === 1) {
        return 'ЧАСТИЧНО';
    }

    if (preg_match('/отмен|заменен|заменён|устарел|superseded|стоп\b|остановлен/u', $lower) === 1) {
        return 'ОТМЕНЁН';
    }

    if (preg_match('/сделан|готов|реализован|done|implemented|closed|закрыт|завершен|завершён/u', $lower) === 1) {
        return 'СДЕЛАНО';
    }

    if (preg_match('/отложен|deferred/u', $lower) === 1) {
        return 'ОТЛОЖЕН';
    }

    if (preg_match('/в работе|в процессе|in_progress|начат|draft|черновик|правка в коде/u', $lower) === 1) {
        return 'В РАБОТЕ';
    }

    if (preg_match('/план|plan\b|todo|ready|approved|backlog|бэклог|идея/u', $lower) === 1) {
        return 'ПЛАН';
    }

    return null;
}

/**
 * Находит первый токен из словаря.
 *
 * @param string $text Текст.
 * @param array<int, string> $vocabulary Допустимые значения.
 *
 * @return string|null Найденный токен или null.
 */
function tokenOf(string $text, array $vocabulary): ?string
{
    $matches = [];
    if (preg_match_all('/~([^~]+)~/u', $text, $matches) < 1) {
        return null;
    }

    foreach ($matches[1] as $found) {
        $token = mb_strtoupper(trim($found));
        if (in_array($token, $vocabulary, true)) {
            return $token;
        }
    }

    return null;
}

/**
 * Определяет слои реализации по токенам или прозе.
 *
 * @param string $text Текст.
 *
 * @return array<int, string> Токены слоёв.
 */
function layersOf(string $text): array
{
    $matches = [];
    preg_match_all('/~([^~]+)~/u', $text, $matches);
    $layers = [];
    foreach ($matches[1] as $found) {
        $token = mb_strtoupper(trim($found));
        if (in_array($token, LAYER_TOKENS, true)) {
            $layers[$token] = true;
        }
    }

    if ($layers === []) {
        $layers = layersFromProse($text);
    }

    $result = [];
    foreach (LAYER_TOKENS as $token) {
        if (isset($layers[$token])) {
            $result[] = $token;
        }
    }

    return $result;
}

/**
 * Определяет слои по словам в тексте.
 *
 * @param string $text Текст.
 *
 * @return array<string, bool> Найденные слои.
 */
function layersFromProse(string $text): array
{
    $lower = mb_strtolower($text);
    $layers = [];
    if (preg_match('/php|бэкенд|бэк\b|backend/u', $lower) === 1) {
        $layers['PHP'] = true;
    }

    if (preg_match('/vue|фронт|frontend|sfc/u', $lower) === 1) {
        $layers['VUE'] = true;
    }

    if (preg_match('/мок|mock/u', $lower) === 1) {
        $layers['МОК'] = true;
    }

    return $layers;
}

/**
 * Извлекает дату из текста или имени файла.
 *
 * @param string $text Текст.
 *
 * @return string|null Дата в формате YYYY-MM-DD.
 */
function extractDate(string $text): ?string
{
    if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/u', $text, $match) === 1) {
        return $match[1];
    }

    return null;
}

/**
 * Возвращает зону документации.
 *
 * @param string $relative Путь от корня репозитория.
 *
 * @return string Зона.
 */
function documentZone(string $relative): string
{
    $parts = explode('/', $relative);
    if (count($parts) > 2 && $parts[1] !== '') {
        return $parts[1];
    }

    return 'docs';
}

/**
 * Определяет тип документа.
 *
 * @param string $relative Путь от корня репозитория.
 *
 * @return string Тип: canon, plan, snapshot, spec, rule, history, doc.
 */
function documentKind(string $relative): string
{
    $name = basename($relative);
    if (str_contains($relative, '/history/')) {
        return 'history';
    }

    if (isPlanName($name)) {
        return 'plan';
    }

    if (preg_match('/baseline|legacy|reconciliation|audit|findings|context/u', $name) === 1) {
        return 'snapshot';
    }

    $zone = documentZone($relative);
    if ($zone === 'specs' || $zone === 'tz') {
        return 'spec';
    }

    if ($zone === 'rule') {
        return 'rule';
    }

    if ($zone === 'review' || $zone === 'old') {
        return 'snapshot';
    }

    return $zone === 'tr' ? 'canon' : 'doc';
}

/**
 * Проверяет, что имя похоже на план или дорожную карту.
 *
 * @param string $name Имя файла.
 *
 * @return bool Признак плана.
 */
function isPlanName(string $name): bool
{
    return preg_match('/(^|[-_])(plan|roadmap|todo)([-_.]|$)/i', $name) === 1;
}

/**
 * Определяет модули по имени документа.
 *
 * @param string $relative Путь от корня репозитория.
 *
 * @return array<int, string> Ключи модулей.
 */
function modulesFromPath(string $relative): array
{
    $slug = preg_replace('/-\d{4}-\d{2}-\d{2}$/u', '', basename($relative, '.md')) ?? '';
    $found = [];
    foreach (MODULE_SLUGS as $prefix => $module) {
        if ($slug === $prefix || str_starts_with($slug, $prefix . '-')) {
            $found[$module] = true;
        }
    }

    return array_keys($found);
}

/**
 * Возвращает заголовок первого уровня.
 *
 * @param string $contents Содержимое файла.
 *
 * @return string Заголовок или пустая строка.
 */
function titleOf(string $contents): string
{
    foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
        if (preg_match('/^#\s+(.+?)\s*$/u', $line, $match) === 1) {
            return $match[1];
        }
    }

    return '';
}

/**
 * Собирает ссылки документа.
 *
 * @param string $contents Содержимое файла.
 * @param string $relative Путь документа от корня репозитория.
 * @param string $repoRoot Корень репозитория.
 *
 * @return array<int, array{target: string|null, raw: string, broken: bool}> Ссылки.
 */
function extractLinks(string $contents, string $relative, string $repoRoot): array
{
    $matches = [];
    preg_match_all('/\[[^\]]*\]\(([^)\s]+)\)/u', $contents, $matches);
    $links = [];
    foreach (array_unique($matches[1]) as $raw) {
        if (str_starts_with($raw, 'http') || str_starts_with($raw, 'mailto:')) {
            continue;
        }

        $links[] = describeLink($relative, $raw, $repoRoot);
    }

    return $links;
}

/**
 * Описывает одну ссылку: цель внутри репозитория и признак битой.
 *
 * @param string $relative Путь документа от корня репозитория.
 * @param string $raw Сырая цель ссылки.
 * @param string $repoRoot Корень репозитория.
 *
 * @return array{target: string|null, raw: string, broken: bool} Ссылка.
 */
function describeLink(string $relative, string $raw, string $repoRoot): array
{
    $clean = explode('#', $raw)[0];
    if ($clean === '' || str_starts_with($raw, '#')) {
        return ['target' => null, 'raw' => $raw, 'broken' => false];
    }

    $target = normalizePath(dirname($relative) . '/' . $clean);
    $exists = is_file($repoRoot . '/' . $target) || is_dir($repoRoot . '/' . $target);

    return ['target' => $target, 'raw' => $raw, 'broken' => !$exists];
}

/**
 * Нормализует путь: убирает . и разворачивает ...
 *
 * @param string $path Путь.
 *
 * @return string Нормализованный путь.
 */
function normalizePath(string $path): string
{
    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }

        if ($part === '..') {
            array_pop($parts);
            continue;
        }

        $parts[] = $part;
    }

    return implode('/', $parts);
}

/**
 * Добавляет входящие ссылки каждому документу.
 *
 * @param array<string, array<string, mixed>> $documents Документы.
 *
 * @return array<string, array<string, mixed>> Документы с backlinks.
 */
function attachBacklinks(array $documents): array
{
    foreach ($documents as $path => $document) {
        foreach ($document['links'] as $link) {
            $target = $link['target'];
            if ($target === null || !isset($documents[$target])) {
                continue;
            }

            $documents[$target]['backlinks'][] = $path;
        }
    }

    foreach (array_keys($documents) as $path) {
        sort($documents[$path]['backlinks']);
    }

    return $documents;
}

/**
 * Считает сводку по документам.
 *
 * @param array<string, array<string, mixed>> $documents Документы.
 *
 * @return array<string, mixed> Сводка.
 */
function summarize(array $documents): array
{
    $byZone = [];
    $byZoneState = [];
    $byState = [];
    $byModule = [];
    $brokenLinks = 0;
    $plans = 0;
    foreach ($documents as $document) {
        $zone = (string) $document['zone'];
        $byZone[$zone] = ($byZone[$zone] ?? 0) + 1;
        $state = $document['state'] ?? 'нет';

        $byState[$state] = ($byState[$state] ?? 0) + 1;
        $byZoneState[$zone][$state] = ($byZoneState[$zone][$state] ?? 0) + 1;
        if ($document['kind'] === 'plan') {
            $plans++;
        }

        $brokenLinks += (int) $document['broken'];
        $module = moduleKeyOf($document);
        $byModule[$module]['docs'] = ($byModule[$module]['docs'] ?? 0) + 1;
        $byModule[$module]['state'][$state] = ($byModule[$module]['state'][$state] ?? 0) + 1;
        foreach ($document['layers'] as $layer) {
            $byModule[$module]['layer'][$layer] = ($byModule[$module]['layer'][$layer] ?? 0) + 1;
        }
    }

    ksort($byZone);
    ksort($byZoneState);
    ksort($byModule);

    return [
        'documents' => count($documents),
        'plans' => $plans,
        'brokenLinks' => $brokenLinks,
        'byZone' => $byZone,
        'byZoneState' => $byZoneState,
        'byState' => $byState,
        'byModule' => $byModule,
    ];
}

/**
 * Возвращает модуль документа для сводки.
 *
 * @param array<string, mixed> $document Документ.
 *
 * @return string Ключ модуля или прочерк.
 */
function moduleKeyOf(array $document): string
{
    $modules = $document['modules'];
    if ($modules === []) {
        return '—';
    }

    return (string) $modules[0];
}

/**
 * Рендерит markdown-карту документации.
 *
 * @param array<string, array<string, mixed>> $documents Документы.
 * @param array<string, mixed> $summary Сводка.
 *
 * @return string Markdown.
 */
function renderMarkdown(array $documents, array $summary, array $allowlist): string
{
    $lines = [
        '# Карта документации',
        '',
        '**СГЕНЕРИРОВАНО.** Это сводка; не редактировать вручную.',
        'Обновление: `composer maps` (из `www/mifrial`); сверка: `composer maps-check`.',
        'Детали по документам — запросом, а не чтением файла:',
        '',
        '```bash',
        'php www/mifrial/tools/doc-index/show.php --module=Game --zone=tr',
        'php www/mifrial/tools/doc-index/show.php --module=Roleplay/Rule --zone=tr --state=ПЛАН',
        'php www/mifrial/tools/doc-index/show.php --zone=tr --kind=plan --no-status',
        'php www/mifrial/tools/doc-index/show.php --broken --limit=0',
        '```',
        '',
        '`doc-index.json` — машинный источник для `show.php`; целиком его не читать.',
        'Карточки `module-map/` — факты из кода; здесь — заявленные статусы документов.',
        sprintf('Документов: %d, планов: %d.', $summary['documents'], $summary['plans']),
        '',
    ];
    $lines = array_merge($lines, renderLegend());
    $lines = array_merge($lines, renderZoneTable($summary));
    $lines = array_merge($lines, renderModuleTable($summary));
    $lines = array_merge($lines, renderLayerSection($documents));
    $lines = array_merge($lines, renderAttention($documents, $allowlist));

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

/**
 * Рендерит схему статусов.
 *
 * @return array<int, string> Строки markdown.
 */
function renderLegend(): array
{
    return [
        '## Схема статусов',
        '',
        '- Состояние (один токен): ' . inlineTokens(STATE_TOKENS) . '.',
        '- Слои реализации: ' . inlineTokens(LAYER_TOKENS) . '.',
        '- Основание: ' . inlineTokens(BASIS_TOKENS) . ' — на что опираться при постройке бэкенда.',
        '',
        'Шапка нового документа:',
        '',
        '```text',
        '**Статус:** ~СДЕЛАНО~',
        '**Реализация:** ~PHP~, ~VUE~',
        '**Опираться на:** ~МОК~',
        '```',
        '',
        'Старый статус прозой распознаётся по строке `**Статус:**`; нераспознанное — в разделе',
        '«Требуют внимания». Исходная строка лежит в `doc-index.json` в поле `statusRaw`.',
        '',
    ];
}

/**
 * Рендерит таблицу состояний по зонам.
 *
 * @param array<string, mixed> $summary Сводка.
 *
 * @return array<int, string> Строки markdown.
 */
function renderZoneTable(array $summary): array
{
    $lines = [
        '## Состояния по зонам',
        '',
        '| Зона | доков | ' . implode(' | ', STATE_TOKENS) . ' | нет |',
        '|---|---|' . str_repeat('---|', count(STATE_TOKENS) + 1),
    ];
    foreach ($summary['byZone'] as $zone => $count) {
        $cells = [];
        foreach (STATE_TOKENS as $token) {
            $cells[] = (string) zoneStateCount($summary, $zone, $token);
        }

        $cells[] = (string) zoneStateCount($summary, $zone, 'нет');
        $lines[] = sprintf('| `%s` | %d | %s |', $zone, $count, implode(' | ', $cells));
    }

    $lines[] = '';

    return $lines;
}

/**
 * Возвращает число документов зоны в состоянии.
 *
 * @param array<string, mixed> $summary Сводка.
 * @param string $zone Зона.
 * @param string $state Состояние.
 *
 * @return int Количество.
 */
function zoneStateCount(array $summary, string $zone, string $state): int
{
    return (int) ($summary['byZoneState'][$zone][$state] ?? 0);
}

/**
 * Рендерит раздел про слои реализации.
 *
 * @param array<string, array<string, mixed>> $documents Документы.
 *
 * @return array<int, string> Строки markdown.
 */
function renderLayerSection(array $documents): array
{
    $notBasis = [];
    $mockOnly = [];
    foreach ($documents as $path => $document) {
        if ($document['basis'] === 'НЕ ОСНОВА') {
            $notBasis[] = $path;
        }

        if (in_array('МОК', $document['layers'], true) && !in_array('PHP', $document['layers'], true)) {
            $mockOnly[] = $path;
        }
    }

    return [
        '## Реализация: где есть PHP и где только мок',
        '',
        '- **Не опираться на реализацию** (`~НЕ ОСНОВА~`): ' . inlinePaths($notBasis) . '.',
        '- **Только мок, PHP нет**: ' . inlinePaths($mockOnly) . '.',
        '- **Есть PHP** — колонка `PHP` в таблице модулей ниже.',
        '',
    ];
}

/**
 * Рендерит матрицу «модуль × состояние × слой».
 *
 * @param array<string, mixed> $summary Сводка.
 *
 * @return array<int, string> Строки markdown.
 */
function renderModuleTable(array $summary): array
{
    $states = ['ПЛАН', 'В РАБОТЕ', 'ЧАСТИЧНО', 'СДЕЛАНО', 'нет'];
    $lines = [
        '## Модуль × состояние × слой',
        '',
        '| Модуль | доков | ' . implode(' | ', $states) . ' | PHP | VUE | МОК |',
        '|---|---|' . str_repeat('---|', count($states) + 3),
    ];
    foreach ($summary['byModule'] as $module => $counters) {
        $cells = [];
        foreach ($states as $state) {
            $cells[] = (string) ($counters['state'][$state] ?? 0);
        }

        foreach (['PHP', 'VUE', 'МОК'] as $layer) {
            $cells[] = (string) ($counters['layer'][$layer] ?? 0);
        }

        $lines[] = sprintf(
            '| %s | %d | %s |',
            moduleCell($module),
            $counters['docs'],
            implode(' | ', $cells),
        );
    }

    $lines[] = '';

    return $lines;
}

/**
 * Рендерит имя модуля ссылкой на его карточку.
 *
 * @param string $module Ключ модуля или прочерк.
 *
 * @return string Ячейка markdown.
 */
function moduleCell(string $module): string
{
    if ($module === '—') {
        return '—';
    }

    if (!is_file(dirname(__DIR__, 4) . '/docs/tr/module-map/' . $module . '.md')) {
        return '`' . $module . '`';
    }

    return relativeLink('docs/tr', 'docs/tr/module-map/' . $module . '.md', $module);
}

/**
 * Рендерит раздел «Требуют внимания».
 *
 * @param array<string, array<string, mixed>> $documents Документы.
 * @param array<string, string> $allowlist Исключения для ссылок.
 *
 * @return array<int, string> Строки markdown.
 */
function renderAttention(array $documents, array $allowlist): array
{
    $unrecognized = [];
    $noStatusPlans = [];
    $broken = [];
    foreach ($documents as $path => $document) {
        if ($document['state'] === null && $document['statusRaw'] !== '' && $document['zone'] === 'tr') {
            $unrecognized[] = $path;
        }

        if ($document['kind'] === 'plan' && $document['statusRaw'] === '' && $document['zone'] === 'tr') {
            $noStatusPlans[] = $path;
        }

        foreach ($document['links'] as $link) {
            if ($link['broken'] === true) {
                $key = $path . '|' . (string) $link['target'];
                $broken[] = sprintf(
                    '- `%s` -> `%s` %s',
                    $path,
                    $link['raw'],
                    isset($allowlist[$key]) ? '— исключение: ' . $allowlist[$key] : '— **не в исключениях**',
                );
            }
        }
    }

    $lines = [
        '## Требуют внимания',
        '',
        sprintf(
            '- **Статус прозой, но не распознан** (%d, `docs/tr`): %s.',
            count($unrecognized),
            inlinePaths($unrecognized, 8),
        ),
        '- Полный список: `show.php --unrecognized --zone=tr --limit=0`.',
        sprintf(
            '- **Планы без строки статуса** (%d): %s.',
            count($noStatusPlans),
            inlinePaths($noStatusPlans, 8),
        ),
        sprintf('- **Битые ссылки** (%d):', count($broken)),
    ];

    if ($broken === []) {
        $lines[] = '- нет';
    }

    $lines = array_merge($lines, $broken);
    $lines[] = '';

    return $lines;
}

/**
 * Рендерит JSON-карту документации.
 *
 * @param array<string, array<string, mixed>> $documents Документы.
 * @param array<string, mixed> $summary Сводка.
 *
 * @return string JSON.
 */
function renderJson(array $documents, array $summary): string
{
    $payload = [
        'generatedBy' => 'composer docs-map',
        'schema' => [
            'state' => STATE_TOKENS,
            'layers' => LAYER_TOKENS,
            'basis' => BASIS_TOKENS,
            'kinds' => KIND_LABELS,
            'header' => '**Статус:**, **Реализация:**, **Опираться на:**',
        ],
        'counts' => [
            'documents' => $summary['documents'],
            'plans' => $summary['plans'],
            'brokenLinks' => $summary['brokenLinks'],
            'byZone' => $summary['byZone'],
            'byZoneState' => $summary['byZoneState'],
            'byState' => $summary['byState'],
        ],
        'modules' => $summary['byModule'],
        'documents' => array_map('leanDocument', array_values($documents)),
    ];

    return (string) json_encode(
        $payload,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    );
}

/**
 * Оставляет в JSON только нужные поля документа.
 *
 * @param array<string, mixed> $document Документ.
 *
 * @return array<string, mixed> Компактное описание.
 */
function leanDocument(array $document): array
{
    $broken = [];
    foreach ($document['links'] as $link) {
        if ($link['broken'] === true) {
            $broken[] = $link['raw'];
        }
    }

    return [
        'path' => $document['path'],
        'title' => $document['title'],
        'zone' => $document['zone'],
        'kind' => $document['kind'],
        'state' => $document['state'],
        'stateSource' => $document['stateSource'],
        'statusRaw' => $document['statusRaw'],
        'layers' => $document['layers'],
        'basis' => $document['basis'],
        'date' => $document['date'],
        'bytes' => $document['bytes'],
        'lines' => $document['lines'],
        'modules' => $document['modules'],
        'linksCount' => count($document['links']),
        'brokenLinks' => $broken,
        'backlinks' => $document['backlinks'],
    ];
}

/**
 * Рендерит токены в строку через запятую.
 *
 * @param array<int, string> $tokens Токены.
 *
 * @return string Текст.
 */
function inlineTokens(array $tokens): string
{
    if ($tokens === []) {
        return '—';
    }

    $rendered = [];
    foreach ($tokens as $token) {
        $rendered[] = '`~' . $token . '~`';
    }

    return implode(', ', $rendered);
}

/**
 * Рендерит пути как относительные ссылки через запятую.
 *
 * @param array<int, string> $paths Пути от корня репозитория.
 * @param int $limit Сколько показать (0 — все).
 *
 * @return string Текст.
 */
function inlinePaths(array $paths, int $limit = 0): string
{
    if ($paths === []) {
        return 'нет';
    }

    $shown = $limit > 0 ? array_slice($paths, 0, $limit) : $paths;
    $rendered = [];
    foreach ($shown as $path) {
        $rendered[] = relativeLink('docs/tr', $path, basename($path));
    }

    if (count($paths) > count($shown)) {
        $rendered[] = sprintf('и ещё %d', count($paths) - count($shown));
    }

    return implode(', ', $rendered);
}

/**
 * Строит относительную markdown-ссылку.
 *
 * @param string $fromDir Каталог ссылающегося документа (от корня репозитория).
 * @param string $target Цель (от корня репозитория).
 * @param string $label Подпись ссылки.
 *
 * @return string Markdown-ссылка.
 */
function relativeLink(string $fromDir, string $target, string $label): string
{
    $fromParts = explode('/', $fromDir);
    $targetParts = explode('/', $target);
    $common = 0;
    while (isset($fromParts[$common], $targetParts[$common]) && $fromParts[$common] === $targetParts[$common]) {
        $common++;
    }

    $up = str_repeat('../', count($fromParts) - $common);
    $href = $up . implode('/', array_slice($targetParts, $common));

    return sprintf('[%s](%s)', $label, $href);
}

/**
 * Падает, если есть незаявленные битые ссылки.
 *
 * @param array<string, array<string, mixed>> $documents Документы.
 * @param array<string, string> $allowlist Исключения.
 *
 * @return void
 */
function checkBrokenLinks(array $documents, array $allowlist): void
{
    $reported = [];
    foreach ($documents as $path => $document) {
        foreach ($document['links'] as $link) {
            if ($link['broken'] !== true) {
                continue;
            }

            $key = $path . '|' . (string) $link['target'];
            if (isset($allowlist[$key])) {
                continue;
            }

            $reported[] = $path . ' -> ' . $link['raw'];
        }
    }

    if ($reported === []) {
        return;
    }

    fail(sprintf(
        "broken links (%d), исправить или внести в tools/doc-index/allowlist.json:\n%s",
        count($reported),
        implode("\n", array_slice($reported, 0, 20)),
    ));
}

/**
 * Падает, если у плана в docs/tr нет распознанного статуса.
 *
 * @param array<string, array<string, mixed>> $documents Документы.
 *
 * @return void
 */
function checkPlanStatuses(array $documents): void
{
    $reported = [];
    foreach ($documents as $path => $document) {
        if ($document['zone'] === 'tr' && $document['kind'] === 'plan' && $document['statusRaw'] === '') {
            $reported[] = $path;
        }
    }

    if ($reported === []) {
        return;
    }

    fail(sprintf(
        "планы без строки статуса (%d), добавить **Статус:** с токеном:\n%s",
        count($reported),
        implode("\n", $reported),
    ));
}

/**
 * Падает, если карта доверия ссылается на отсутствующие документы.
 *
 * @param string $repoRoot Корень репозитория.
 *
 * @return void
 */
function checkTrustMap(string $repoRoot): void
{
    $path = $repoRoot . '/docs/tr/contract-status.md';
    if (!is_file($path)) {
        fail('contract-status.md is missing: ' . $path);
    }

    $contents = (string) file_get_contents($path);
    $reported = [];
    $matches = [];
    preg_match_all('/\]\(([^)\s]+)\)/u', $contents, $matches);
    foreach ($matches[1] as $raw) {
        if (str_starts_with($raw, 'http') || str_starts_with($raw, '#')) {
            continue;
        }

        $target = normalizePath('docs/tr/' . explode('#', $raw)[0]);
        if (!is_file($repoRoot . '/' . $target) && !is_dir($repoRoot . '/' . $target)) {
            $reported[] = 'docs/tr/contract-status.md -> ' . $raw;
        }
    }

    $matches = [];
    preg_match_all('/`((?:www|docs)\/[^`]+\.md)`/u', $contents, $matches);
    foreach ($matches[1] as $raw) {
        if (!is_file($repoRoot . '/' . $raw)) {
            $reported[] = 'docs/tr/contract-status.md -> ' . $raw;
        }
    }

    if ($reported === []) {
        return;
    }

    fail(sprintf(
        "карта доверия ссылается на отсутствующее (%d):\n%s",
        count($reported),
        implode("\n", $reported),
    ));
}

/**
 * Записывает файл карты.
 *
 * @param string $path Путь.
 * @param string $contents Содержимое.
 *
 * @return void
 */
function writeFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        fail('cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        fail('cannot write file: ' . $path);
    }
}

/**
 * Сверяет файл с ожидаемым содержимым.
 *
 * @param string $path Путь.
 * @param string $expected Ожидаемое содержимое.
 * @param string $label Имя артефакта для сообщения.
 *
 * @return void
 */
function failUnlessSame(string $path, string $expected, string $label): void
{
    $actual = is_file($path) ? (string) file_get_contents($path) : '';
    if ($actual !== $expected) {
        fail($label . ' is out of date, run composer docs-map: ' . $path);
    }
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
