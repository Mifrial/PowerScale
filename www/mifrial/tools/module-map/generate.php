<?php

declare(strict_types=1);

/**
 * Собирает карточки модулей Mifrial из module.config.php и doc-index.json.
 *
 * Запуск: composer maps (запись) или composer maps-check (сверка).
 * Файл не подпадает под phpcs: правила стандарта соблюдаются вручную.
 */

const PLAN_STATE_ORDER = [
    'ПЛАН' => 0,
    'В РАБОТЕ' => 1,
    'ЧАСТИЧНО' => 2,
    'ОТМЕНЁН' => 3,
    'ОТЛОЖЕН' => 4,
    'СДЕЛАНО' => 5,
    'СНИМОК' => 6,
];

const TRUSTED_MODULES = [
    'Core/SmartTable',
    'Core/Kernel',
    'Core/Event',
    'Core/Cache',
    'Core/Auth',
    'Core/Logger',
];

$mapRoot = dirname(__DIR__, 2);
$docsRoot = dirname($mapRoot, 2) . '/docs/tr';
$checkOnly = in_array('--check', $argv, true);

$configs = discoverModuleConfigs($mapRoot);
$namespaces = readComposerNamespaces($mapRoot);
$modules = collectModules($configs, $namespaces, $mapRoot);
$plans = readDocumentPlans($docsRoot . '/doc-index.json');

$sliceRoot = $docsRoot . '/module-map';
$indexMarkdown = renderIndex($modules, $plans);
$json = renderJson($modules, $plans) . PHP_EOL;
$slices = renderSlices($modules, $plans);

if ($checkOnly) {
    failUnlessSame($sliceRoot . '/index.md', $indexMarkdown, 'module-map/index.md');
    failUnlessSame($docsRoot . '/module-map.json', $json, 'module-map.json');
    checkSlices($sliceRoot, $slices);
    fwrite(STDOUT, 'module map is up to date' . PHP_EOL);
    exit(0);
}

writeFile($sliceRoot . '/index.md', $indexMarkdown);
writeFile($docsRoot . '/module-map.json', $json);
foreach ($slices as $relativePath => $contents) {
    writeFile($sliceRoot . '/' . $relativePath, $contents);
}

removeStaleSlices($sliceRoot, array_keys($slices));
fwrite(STDOUT, sprintf('module map written: %d modules, %d slices%s', count($modules), count($slices), PHP_EOL));
exit(0);

/**
 * Возвращает описания всех module.config.php на диске.
 *
 * @param string $root Корень www/mifrial.
 *
 * @return array<int, array{group: string, name: string, config: string, dir: string}> Модули.
 */
function discoverModuleConfigs(string $root): array
{
    $modulesRoot = $root . '/modules';
    $found = [];
    foreach (glob($modulesRoot . '/*', GLOB_ONLYDIR) ?: [] as $groupDirectory) {
        foreach (glob($groupDirectory . '/*', GLOB_ONLYDIR) ?: [] as $moduleDirectory) {
            $configPath = $moduleDirectory . '/module.config.php';
            if (!is_file($configPath)) {
                continue;
            }

            $found[] = [
                'group' => basename($groupDirectory),
                'name' => basename($moduleDirectory),
                'config' => $configPath,
                'dir' => $moduleDirectory,
            ];
        }
    }

    return $found;
}

/**
 * Читает карту PSR-4: путь относительно www/mifrial -> неймспейс модуля.
 *
 * @param string $root Корень www/mifrial.
 *
 * @return array<string, string> Путь -> неймспейс без хвоста.
 */
function readComposerNamespaces(string $root): array
{
    $composerPath = $root . '/composer.json';
    if (!is_file($composerPath)) {
        fail('composer.json is missing: ' . $composerPath);
    }

    $composer = json_decode((string) file_get_contents($composerPath), true);
    $psr4 = is_array($composer) ? ($composer['autoload']['psr-4'] ?? []) : [];
    if (!is_array($psr4)) {
        return [];
    }

    $namespaces = [];
    foreach ($psr4 as $namespace => $paths) {
        foreach ((array) $paths as $path) {
            $namespaces[rtrim((string) $path, '/')] = rtrim((string) $namespace, '\\');
        }
    }

    return $namespaces;
}

/**
 * Собирает реестр модулей: конфиг, контракты и рёбра зависимостей.
 *
 * @param array<int, array{group: string, name: string, config: string, dir: string}> $configs Модули.
 * @param array<string, string> $namespaces Карта PSR-4.
 * @param string $root Корень www/mifrial.
 *
 * @return array<int, array<string, mixed>> Реестр.
 */
function collectModules(array $configs, array $namespaces, string $root): array
{
    $moduleNamespaces = [];
    foreach ($configs as $config) {
        $relativePath = moduleRelativePath($config['dir'], $root);
        $moduleNamespaces[$config['group'] . '/' . $config['name']]
            = $namespaces[$relativePath] ?? ('Mifrial\\' . $config['group'] . '\\' . $config['name']);
    }

    $modules = [];
    foreach ($configs as $config) {
        $moduleConfig = require $config['config'];
        $key = $config['group'] . '/' . $config['name'];
        $modules[$key] = [
            'key' => $key,
            'group' => $config['group'],
            'name' => $config['name'],
            'path' => moduleRelativePath($config['dir'], $root),
            'namespace' => $moduleNamespaces[$key],
            'container' => scalarOrNull($moduleConfig['container'] ?? null),
            'locator' => scalarOrNull($moduleConfig['locator'] ?? null),
            'setup' => describeSetup($moduleConfig['setup'] ?? null),
            'requestBind' => scalarOrNull($moduleConfig['request_bind'] ?? null),
            'ports' => array_values(array_map('strval', array_keys((array) ($moduleConfig['ports'] ?? [])))),
            'routes' => collectRoutes((array) ($moduleConfig['routes'] ?? [])),
            'events' => array_values(array_map('strval', (array) ($moduleConfig['events'] ?? []))),
            'contracts' => collectContracts($config['dir']),
            'readme' => is_file($config['dir'] . '/README.md'),
            'dependsOn' => [],
            'dependedOnBy' => [],
        ];
    }

    foreach ($modules as $key => $module) {
        $modules[$key]['dependsOn'] = collectDependencies(
            $module['path'],
            $root,
            $moduleNamespaces,
            $key,
        );
    }

    foreach ($modules as $key => $module) {
        foreach ($module['dependsOn'] as $target) {
            if (isset($modules[$target])) {
                $modules[$target]['dependedOnBy'][] = $key;
            }
        }
    }

    ksort($modules);
    foreach ($modules as $key => $module) {
        sort($modules[$key]['dependedOnBy']);
    }

    return array_values($modules);
}

/**
 * Возвращает путь модуля относительно www/mifrial.
 *
 * @param string $moduleDir Абсолютный путь модуля.
 * @param string $root Корень www/mifrial.
 *
 * @return string Относительный путь.
 */
function moduleRelativePath(string $moduleDir, string $root): string
{
    return ltrim(str_replace($root, '', $moduleDir), '/');
}

/**
 * Приводит значение к строке или null.
 *
 * @param mixed $value Значение из конфига.
 *
 * @return string|null Строка или null.
 */
function scalarOrNull(mixed $value): ?string
{
    return is_string($value) ? $value : null;
}

/**
 * Описывает ключ setup: class-string или Closure.
 *
 * @param mixed $setup Значение ключа setup.
 *
 * @return string|null Описание или null.
 */
function describeSetup(mixed $setup): ?string
{
    if (is_string($setup)) {
        return $setup;
    }

    return $setup instanceof Closure ? 'Closure(IServiceLocator)' : null;
}

/**
 * Разбирает ключ routes в плоский список.
 *
 * @param array<mixed> $routes Карта маршрутов.
 *
 * @return array<int, array{action: string, handler: string, csrf: bool}> Маршруты.
 */
function collectRoutes(array $routes): array
{
    $collected = [];
    foreach ($routes as $action => $route) {
        $routeData = is_array($route) ? $route : [];
        $collected[] = [
            'action' => (string) $action,
            'handler' => scalarOrNull($routeData['handler'] ?? null) ?? '—',
            'csrf' => ($routeData['csrf'] ?? true) === true,
        ];
    }

    return $collected;
}

/**
 * Перечисляет контракты каталога Interface с их кратким описанием.
 *
 * @param string $moduleDir Абсолютный путь модуля.
 *
 * @return array<int, array{name: string, path: string, summary: string}> Контракты.
 */
function collectContracts(string $moduleDir): array
{
    $interfaceDir = $moduleDir . '/Interface';
    if (!is_dir($interfaceDir)) {
        return [];
    }

    $contracts = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($interfaceDir, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = ltrim(str_replace($moduleDir, '', $file->getPathname()), '/');
        $contracts[] = [
            'name' => $file->getBasename('.php'),
            'path' => $relativePath,
            'summary' => summarizeDocblock($file->getPathname()),
        ];
    }

    usort($contracts, static fn (array $left, array $right): int => $left['path'] <=> $right['path']);

    return $contracts;
}

/**
 * Возвращает первую строку описания PHPDoc типа.
 *
 * @param string $filePath Путь PHP-файла.
 *
 * @return string Описание или пустая строка.
 */
function summarizeDocblock(string $filePath): string
{
    $contents = (string) file_get_contents($filePath);
    $blocks = [];
    if (preg_match_all('#/\*\*(.*?)\*/#s', $contents, $blocks) < 1) {
        return '';
    }

    foreach (preg_split('/\r\n|\r|\n/', $blocks[1][0]) ?: [] as $line) {
        $summary = trim(ltrim(trim($line), '*'));
        if ($summary !== '' && !str_starts_with($summary, '@')) {
            return $summary;
        }
    }

    return '';
}

/**
 * Собирает рёбра зависимостей модуля по импортам Mifrial.
 *
 * @param string $modulePath Путь модуля относительно www/mifrial.
 * @param string $root Корень www/mifrial.
 * @param array<string, string> $moduleNamespaces Неймспейсы модулей.
 * @param string $selfKey Ключ самого модуля.
 *
 * @return array<int, string> Ключи модулей, от которых есть зависимость.
 */
function collectDependencies(
    string $modulePath,
    string $root,
    array $moduleNamespaces,
    string $selfKey,
): array {
    $dependencies = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $modulePath, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        if (str_contains($file->getPathname(), '/tests/')) {
            continue;
        }

        foreach (referencedNamespaceOwners((string) file_get_contents($file->getPathname()), $moduleNamespaces) as $owner) {
            if ($owner !== $selfKey) {
                $dependencies[$owner] = true;
            }
        }
    }

    $keys = array_keys($dependencies);
    sort($keys);

    return $keys;
}

/**
 * Находит модули-владельцы для всех Mifrial-неймспейсов файла.
 *
 * @param string $contents Исходник.
 * @param array<string, string> $moduleNamespaces Неймспейсы модулей.
 *
 * @return array<int, string> Ключи модулей без повторов.
 */
function referencedNamespaceOwners(string $contents, array $moduleNamespaces): array
{
    $matches = [];
    if (preg_match_all('/Mifrial\\\\[A-Za-z0-9_\\\\]+/', $contents, $matches) < 1) {
        return [];
    }

    $owners = [];
    foreach ($matches[0] as $reference) {
        $owner = longestNamespaceOwner($reference, $moduleNamespaces);
        if ($owner !== null) {
            $owners[$owner] = true;
        }
    }

    return array_keys($owners);
}

/**
 * Находит модуль с самым длинным подходящим неймспейсом.
 *
 * @param string $reference Ссылка на тип Mifrial.
 * @param array<string, string> $moduleNamespaces Неймспейсы модулей.
 *
 * @return string|null Ключ модуля или null.
 */
function longestNamespaceOwner(string $reference, array $moduleNamespaces): ?string
{
    $bestKey = null;
    $bestLength = 0;
    foreach ($moduleNamespaces as $key => $namespace) {
        $prefix = $namespace . '\\';
        if (str_starts_with($reference, $prefix) && strlen($prefix) > $bestLength) {
            $bestKey = $key;
            $bestLength = strlen($prefix);
        }
    }

    return $bestKey;
}

/**
 * Рендерит короткий индекс реестра со ссылками на карточки модулей.
 *
 * @param array<int, array<string, mixed>> $modules Реестр.
 * @param array<string, array<int, array<string, mixed>>> $plans Планы по модулям.
 *
 * @return string Markdown.
 */
function renderIndex(array $modules, array $plans): string
{
    $lines = [
        '# Реестр модулей',
        '',
        '**СГЕНЕРИРОВАНО.** Не редактировать вручную.',
        'Команда: `composer maps` (из `www/mifrial`) — обновляет `doc-index` и реестр;',
        'сверка — `composer maps-check`.',
        'Карточки: `<Группа>/<Модуль>.md` рядом с этим файлом; машиночитаемо — `../module-map.json`.',
        'Планы в карточках — из `doc-index.json` (`composer docs-map`).',
        '',
        '## Сводка',
        '',
        '| Группа | Модуль | Локатор | Портов | Маршрутов | Зависит от | Документов |',
        '|---|---|---|---|---|---|---|',
    ];

    foreach ($modules as $module) {
        $lines[] = sprintf(
            '| %s | %s | %s | %d | %d | %s | %d |',
            $module['group'],
            relativeLink('docs/tr/module-map', 'docs/tr/module-map/' . slicePath($module), $module['name']),
            $module['locator'] === null ? '—' : '`' . shortName($module['locator']) . '`',
            count($module['ports']),
            count($module['routes']),
            inlineList($module['dependsOn']),
            count($plans[$module['key']] ?? []),
        );
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

/**
 * Рендерит карточки всех модулей.
 *
 * @param array<int, array<string, mixed>> $modules Реестр.
 * @param array<string, array<int, array<string, mixed>>> $plans Планы по модулям.
 *
 * @return array<string, string> Markdown по относительному пути карточки.
 */
function renderSlices(array $modules, array $plans): array
{
    $slices = [];
    foreach ($modules as $module) {
        $slices[slicePath($module)] = renderSlice($module, $plans[$module['key']] ?? []);
    }

    return $slices;
}

/**
 * Возвращает путь карточки относительно каталога module-map.
 *
 * @param array<string, mixed> $module Модуль.
 *
 * @return string Относительный путь.
 */
function slicePath(array $module): string
{
    return $module['group'] . '/' . $module['name'] . '.md';
}

/**
 * Рендерит карточку одного модуля.
 *
 * @param array<string, mixed> $module Модуль.
 * @param array<int, array<string, mixed>> $plans Документы модуля.
 *
 * @return string Markdown.
 */
function renderSlice(array $module, array $plans): string
{
    $fromDir = 'docs/tr/module-map/' . $module['group'];
    $lines = [
        '# ' . $module['key'],
        '',
        '**СГЕНЕРИРОВАНО.** Не редактировать вручную.',
        'Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.',
        'Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.',
        'Карточка небольшая: открывать можно. Детали по планам — `show.php --module='
            . $module['key'] . ' --zone=tr`.',
        '',
        '- **Путь:** `' . $module['path'] . '`',
        '- **Неймспейс:** `' . $module['namespace'] . '`',
        '- **Контейнер:** ' . describeClass($module['container']),
        '- **Локатор:** ' . describeClass($module['locator']),
        '- **Setup:** ' . describeClass($module['setup']),
    ];

    if ($module['requestBind'] !== null) {
        $lines[] = '- **`request_bind`:** `' . shortName($module['requestBind']) . '`';
    }

    if ($module['readme'] === true) {
        $lines[] = '- **Карта входа:** ' . relativeLink(
            $fromDir,
            'www/mifrial/' . $module['path'] . '/README.md',
            'README.md',
        );
    }

    $lines[] = '- **Канон:** ' . canonLine($module['key'], $fromDir);
    $lines[] = '';
    $lines[] = '## Порты';
    $lines[] = '';
    $lines = array_merge($lines, bulletList($module['ports'], 'нет'));
    $lines[] = '';
    $lines[] = '## Маршруты';
    $lines[] = '';
    $lines[] = '| Action | Handler | CSRF |';
    $lines[] = '|---|---|---|';
    foreach ($module['routes'] as $route) {
        $lines[] = sprintf(
            '| `%s` | `%s` | %s |',
            $route['action'],
            shortName($route['handler']),
            $route['csrf'] ? 'да' : 'нет',
        );
    }

    if ($module['routes'] === []) {
        $lines[] = '| — | — | — |';
    }

    $lines[] = '';
    $lines[] = '## Контракты `Interface/`';
    $lines[] = '';
    foreach ($module['contracts'] as $contract) {
        $description = $contract['summary'] === '' ? '' : ' — ' . $contract['summary'];
        $lines[] = '- `' . $contract['path'] . '`' . $description;
    }

    if ($module['contracts'] === []) {
        $lines[] = '- нет';
    }

    $lines[] = '';
    $lines[] = '## Зависимости';
    $lines[] = '';
    $lines[] = '- **Зависит от:** ' . moduleLinks($module['dependsOn'], $fromDir);
    $lines[] = '- **От него зависят:** ' . moduleLinks($module['dependedOnBy'], $fromDir);
    $lines[] = '';
    $lines[] = '## События';
    $lines[] = '';
    $lines[] = inlineList($module['events']);
    $lines[] = '';
    $lines = array_merge($lines, renderPlansSection($plans, $fromDir));

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

/**
 * Рендерит раздел «Планы и документы» карточки модуля.
 *
 * @param array<int, array<string, mixed>> $plans Документы модуля.
 * @param string $fromDir Каталог карточки от корня репозитория.
 *
 * @return array<int, string> Строки markdown.
 */
function renderPlansSection(array $plans, string $fromDir): array
{
    if ($plans === []) {
        return ['## Планы и документы', '', 'нет', ''];
    }

    $lines = [
        '## Планы и документы',
        '',
        '| Документ | Тип | Состояние | Дата |',
        '|---|---|---|---|',
    ];
    foreach ($plans as $plan) {
        $lines[] = sprintf(
            '| %s | %s | %s | %s |',
            relativeLink($fromDir, (string) $plan['path'], basename((string) $plan['path'])),
            $plan['kind'] === 'plan' ? 'план' : 'канон',
            planStateCell($plan),
            $plan['date'] ?? '—',
        );
    }

    $lines[] = '';

    return $lines;
}

/**
 * Возвращает ячейку состояния документа.
 *
 * @param array<string, mixed> $plan Документ.
 *
 * @return string Токен, `?` или прочерк.
 */
function planStateCell(array $plan): string
{
    if ($plan['state'] !== null) {
        return '`~' . $plan['state'] . '~`';
    }

    return $plan['hasStatus'] === true ? '?' : '—';
}

/**
 * Описывает канон модуля: карта доверия есть только для стабильных Core.
 *
 * @param string $key Ключ модуля.
 * @param string $fromDir Каталог карточки от корня репозитория.
 *
 * @return string Описание канона.
 */
function canonLine(string $key, string $fromDir): string
{
    if (!in_array($key, TRUSTED_MODULES, true)) {
        return 'не зафиксирован';
    }

    return 'раздел «Карта доверия: стабильные модули Core» в '
        . relativeLink($fromDir, 'docs/tr/contract-status.md', 'contract-status.md');
}

/**
 * Рендерит ключи модулей ссылками на их карточки.
 *
 * @param array<int, string> $keys Ключи модулей.
 * @param string $fromDir Каталог карточки от корня репозитория.
 *
 * @return string Текст.
 */
function moduleLinks(array $keys, string $fromDir): string
{
    if ($keys === []) {
        return '—';
    }

    $links = [];
    foreach ($keys as $key) {
        $links[] = relativeLink($fromDir, 'docs/tr/module-map/' . $key . '.md', $key);
    }

    return implode(', ', $links);
}

/**
 * Читает планы и канон модулей из doc-index.json.
 *
 * @param string $path Путь к doc-index.json.
 *
 * @return array<string, array<int, array<string, mixed>>> Документы по модулям.
 */
function readDocumentPlans(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    $documents = is_array($decoded) ? ($decoded['documents'] ?? []) : [];
    $plans = [];
    foreach ((array) $documents as $document) {
        if (!is_array($document) || !in_array($document['kind'] ?? '', ['plan', 'canon'], true)) {
            continue;
        }

        $entry = [
            'path' => (string) ($document['path'] ?? ''),
            'title' => (string) ($document['title'] ?? ''),
            'kind' => (string) $document['kind'],
            'state' => $document['state'] ?? null,
            'date' => $document['date'] ?? null,
            'hasStatus' => (string) ($document['statusRaw'] ?? '') !== '',
        ];
        foreach ((array) ($document['modules'] ?? []) as $module) {
            $plans[(string) $module][] = $entry;
        }
    }

    foreach (array_keys($plans) as $module) {
        usort($plans[$module], 'comparePlans');
    }

    return $plans;
}

/**
 * Сравнивает документы: сначала открытые, затем по пути.
 *
 * @param array<string, mixed> $left Документ.
 * @param array<string, mixed> $right Документ.
 *
 * @return int Результат сравнения.
 */
function comparePlans(array $left, array $right): int
{
    $leftRank = PLAN_STATE_ORDER[$left['state'] ?? ''] ?? 9;
    $rightRank = PLAN_STATE_ORDER[$right['state'] ?? ''] ?? 9;
    if ($leftRank !== $rightRank) {
        return $leftRank <=> $rightRank;
    }

    return (string) $left['path'] <=> (string) $right['path'];
}

/**
 * Строит относительную markdown-ссылку.
 *
 * @param string $fromDir Каталог ссылающегося документа от корня репозитория.
 * @param string $target Цель от корня репозитория.
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

    return sprintf('[%s](%s)', $label, $up . implode('/', array_slice($targetParts, $common)));
}

/**
 * Сверяет карточки с ожидаемым содержимым и ищет лишние файлы.
 *
 * @param string $sliceRoot Каталог module-map.
 * @param array<string, string> $slices Ожидаемые карточки.
 *
 * @return void
 */
function checkSlices(string $sliceRoot, array $slices): void
{
    foreach ($slices as $relativePath => $contents) {
        failUnlessSame($sliceRoot . '/' . $relativePath, $contents, 'module-map/' . $relativePath);
    }

    $extra = array_diff(findSliceFiles($sliceRoot), array_keys($slices));
    if ($extra === []) {
        return;
    }

    fail(sprintf(
        "устаревшие карточки модулей (%d), запустить composer maps:\n%s",
        count($extra),
        implode("\n", $extra),
    ));
}

/**
 * Удаляет карточки модулей, которых больше нет в реестре.
 *
 * @param string $sliceRoot Каталог module-map.
 * @param array<int, string> $expected Ожидаемые карточки.
 *
 * @return void
 */
function removeStaleSlices(string $sliceRoot, array $expected): void
{
    foreach (array_diff(findSliceFiles($sliceRoot), $expected) as $relativePath) {
        $path = $sliceRoot . '/' . $relativePath;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

/**
 * Перечисляет markdown-карточки в каталоге module-map.
 *
 * @param string $sliceRoot Каталог module-map.
 *
 * @return array<int, string> Пути относительно каталога, кроме index.md.
 */
function findSliceFiles(string $sliceRoot): array
{
    if (!is_dir($sliceRoot)) {
        return [];
    }

    $found = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sliceRoot, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'md') {
            continue;
        }

        $relativePath = ltrim(str_replace($sliceRoot, '', $file->getPathname()), '/');
        if ($relativePath !== 'index.md') {
            $found[] = $relativePath;
        }
    }

    sort($found);

    return $found;
}

/**
 * Возвращает короткое имя типа без неймспейса.
 *
 * @param string $type Полное имя типа.
 *
 * @return string Короткое имя.
 */
function shortName(string $type): string
{
    $parts = explode('\\', $type);

    return (string) end($parts);
}

/**
 * Описывает class-string как код или прочерк.
 *
 * @param string|null $type Значение.
 *
 * @return string Описание.
 */
function describeClass(?string $type): string
{
    return $type === null ? '—' : '`' . $type . '`';
}

/**
 * Рендерит список как markdown-пункты.
 *
 * @param array<int, string> $values Значения.
 * @param string $emptyText Текст при пустом списке.
 *
 * @return array<int, string> Строки.
 */
function bulletList(array $values, string $emptyText): array
{
    if ($values === []) {
        return ['- ' . $emptyText];
    }

    $lines = [];
    foreach ($values as $value) {
        $lines[] = '- `' . $value . '`';
    }

    return $lines;
}

/**
 * Рендерит значения в строку через запятую.
 *
 * @param array<int, string> $values Значения.
 *
 * @return string Текст.
 */
function inlineList(array $values): string
{
    return $values === [] ? '—' : implode(', ', $values);
}

/**
 * Рендерит JSON-реестр.
 *
 * @param array<int, array<string, mixed>> $modules Реестр.
 * @param array<string, array<int, array<string, mixed>>> $plans Планы по модулям.
 *
 * @return string JSON.
 */
function renderJson(array $modules, array $plans): string
{
    foreach ($modules as $index => $module) {
        $modules[$index]['plans'] = $plans[$module['key']] ?? [];
    }

    $payload = [
        'generatedBy' => 'composer maps',
        'plansSource' => 'docs/tr/doc-index.json',
        'modules' => $modules,
    ];

    return (string) json_encode(
        $payload,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    );
}

/**
 * Записывает файл реестра.
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
        fail($label . ' is out of date, run composer maps: ' . $path);
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
