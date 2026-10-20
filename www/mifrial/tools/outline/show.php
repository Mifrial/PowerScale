<?php

declare(strict_types=1);

/**
 * Печатает карту файла кода: классы, методы и объявления с диапазонами строк.
 *
 * Запуск: php tools/outline/show.php [--big] [--json] <файл>...
 * Файл не подпадает под phpcs: правила стандарта соблюдаются вручную.
 */

const READ_WHOLE_LIMIT = 300;
const READ_WHOLE_BYTES = 60000;
const BIG_METHOD_LINES = 30;
const BIG_DECLARATION_LINES = 40;
const FRONTEND_EXTENSIONS = ['vue', 'ts', 'js'];

$arguments = array_slice($argv, 1);
if ($arguments === [] || in_array('--help', $arguments, true) || in_array('-h', $arguments, true)) {
    printUsage();
    exit($arguments === [] ? 2 : 0);
}

$bigOnly = in_array('--big', $arguments, true);
$json = in_array('--json', $arguments, true);
$paths = [];
foreach ($arguments as $argument) {
    if ($argument === '--big' || $argument === '--json') {
        continue;
    }

    if (str_starts_with($argument, '--')) {
        fail('неизвестный флаг: ' . $argument . ' (см. --help)');
    }

    $paths[] = $argument;
}

if ($paths === []) {
    fail('не указан файл (см. --help)');
}

$files = [];
foreach ($paths as $path) {
    $files[] = outlineFile($path);
}

if ($json) {
    fwrite(STDOUT, json_encode($files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    exit(0);
}

$rendered = [];
foreach ($files as $file) {
    $rendered[] = renderResult($file, $bigOnly);
}

fwrite(STDOUT, implode("\n\n", $rendered) . "\n");
exit(0);

/**
 * Собирает карту одного файла.
 *
 * @param string $path Путь к файлу.
 *
 * @return array<string, mixed> Карта файла.
 */
function outlineFile(string $path): array
{
    if (!is_file($path)) {
        fail('нет файла: ' . $path);
    }

    $code = (string) file_get_contents($path);
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $lines = countLines($code);
    $file = [
        'path' => $path,
        'bytes' => strlen($code),
        'lines' => $lines,
        'extension' => $extension,
        'eligible' => $lines <= READ_WHOLE_LIMIT && strlen($code) <= READ_WHOLE_BYTES,
        'classes' => [],
        'functions' => [],
        'blocks' => [],
        'declarations' => [],
    ];

    if ($extension === 'php') {
        $parsed = parsePhp($code);
        $file['classes'] = $parsed['classes'];
        $file['functions'] = $parsed['functions'];
    } elseif (in_array($extension, FRONTEND_EXTENSIONS, true)) {
        $parsed = parseFrontend($code, $extension);
        $file['blocks'] = $parsed['blocks'];
        $file['declarations'] = $parsed['declarations'];
    }

    return $file;
}

/**
 * Считает строки в тексте.
 *
 * @param string $code Текст файла.
 *
 * @return int Число строк.
 */
function countLines(string $code): int
{
    if ($code === '') {
        return 0;
    }

    return substr_count($code, "\n") + (str_ends_with($code, "\n") ? 0 : 1);
}

/**
 * Разбирает PHP через token_get_all.
 *
 * @param string $code Текст файла.
 *
 * @return array<string, array<int, array<string, mixed>>> Классы и функции.
 */
function parsePhp(string $code): array
{
    $state = [
        'classes' => [],
        'classStack' => [],
        'pendingClass' => null,
        'pendingMethod' => null,
        'methodStack' => [],
        'functions' => [],
        'depth' => 0,
    ];

    $tokens = token_get_all($code);
    $line = 1;
    foreach ($tokens as $index => $token) {
        $id = is_array($token) ? $token[0] : null;
        $text = is_array($token) ? $token[1] : $token;
        if (is_array($token)) {
            $line = $token[2];
        }

        handlePhpToken($state, $tokens, $index, $id, $text, $line);
        $line += substr_count($text, "\n");
    }

    return [
        'classes' => finalizeClasses($state['classes']),
        'functions' => finalizeMethods($state['functions']),
    ];
}

/**
 * Обрабатывает один токен PHP.
 *
 * @param array<string, mixed> $state Состояние разбора.
 * @param array<int, mixed> $tokens Все токены файла.
 * @param int $index Позиция токена.
 * @param int|null $id Идентификатор токена.
 * @param string $text Текст токена.
 * @param int $line Номер строки.
 *
 * @return void
 */
function handlePhpToken(array &$state, array $tokens, int $index, ?int $id, string $text, int $line): void
{
    if ($id !== null && in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
        $previous = previousSignificant($tokens, $index);
        if ($id !== T_CLASS || ($previous !== null && $previous[0] !== T_DOUBLE_COLON)) {
            $anonymous = $previous !== null && $previous[0] === T_NEW;
            $state['pendingClass'] = [
                'kind' => kindOfToken($id),
                'start' => $line,
                'name' => '',
                'anonymous' => $anonymous,
            ];
        }

        return;
    }

    if ($id === T_STRING && isPendingName($state['pendingClass']) && !$state['pendingClass']['anonymous']) {
        $state['pendingClass']['name'] = $text;

        return;
    }

    if ($id === T_FUNCTION) {
        $state['pendingMethod'] = startMethod($state, $tokens, $index, $line);

        return;
    }

    if ($id === T_STRING && isPendingName($state['pendingMethod'])) {
        $state['pendingMethod']['name'] = $text;

        return;
    }

    if ($text === '{') {
        openBrace($state);

        return;
    }

    if ($text === '}') {
        closeBrace($state, $line);

        return;
    }

    if ($text === ';' && $state['pendingMethod'] !== null) {
        finishMethod($state, $line);
    }
}

/**
 * Проверяет, что сущность ждёт имя.
 *
 * @param array<string, mixed>|null $entity Класс или метод.
 *
 * @return bool Ожидание имени.
 */
function isPendingName(?array $entity): bool
{
    return $entity !== null && $entity['name'] === '';
}

/**
 * Начинает метод, если функция не является замыканием.
 *
 * @param array<string, mixed> $state Состояние разбора.
 * @param array<int, mixed> $tokens Все токены файла.
 * @param int $index Позиция T_FUNCTION.
 * @param int $line Номер строки.
 *
 * @return array<string, mixed>|null Метод или null для замыкания.
 */
function startMethod(array $state, array $tokens, int $index, int $line): ?array
{
    $next = nextSignificant($tokens, $index);
    if ($next !== null && $next[1] === '&') {
        $next = nextSignificant($tokens, $next[2]);
    }

    if ($next === null || $next[0] !== T_STRING) {
        return null;
    }

    $owner = null;
    if ($state['classStack'] !== []) {
        $owner = $state['classStack'][count($state['classStack']) - 1];
        if ($state['depth'] !== $state['classes'][$owner]['bodyDepth']) {
            return null;
        }
    } elseif ($state['depth'] !== 0) {
        return null;
    }

    return [
        'owner' => $owner,
        'name' => '',
        'visibility' => $owner === null ? null : methodVisibility($tokens, $index),
        'start' => $line,
        'end' => $line,
    ];
}

/**
 * Открывает область класса или метода.
 *
 * @param array<string, mixed> $state Состояние разбора.
 *
 * @return void
 */
function openBrace(array &$state): void
{
    $state['depth']++;
    if ($state['pendingClass'] !== null && $state['pendingClass']['name'] !== '') {
        $class = $state['pendingClass'];
        $class['bodyDepth'] = $state['depth'];
        $class['end'] = $state['pendingClass']['start'];
        $class['methods'] = [];
        $state['classes'][] = $class;
        $state['classStack'][] = count($state['classes']) - 1;
    } elseif ($state['pendingMethod'] !== null) {
        $state['pendingMethod']['bodyDepth'] = $state['depth'];
        $state['methodStack'][] = $state['pendingMethod'];
    }

    $state['pendingClass'] = null;
    $state['pendingMethod'] = null;
}

/**
 * Закрывает область класса или метода.
 *
 * @param array<string, mixed> $state Состояние разбора.
 * @param int $line Номер строки.
 *
 * @return void
 */
function closeBrace(array &$state, int $line): void
{
    $methodCount = count($state['methodStack']);
    if ($methodCount > 0 && $state['methodStack'][$methodCount - 1]['bodyDepth'] === $state['depth']) {
        $method = array_pop($state['methodStack']);
        $method['end'] = $line;
        addMethod($state, $method);
    }

    $classCount = count($state['classStack']);
    if ($classCount > 0 && $state['classes'][$state['classStack'][$classCount - 1]]['bodyDepth'] === $state['depth']) {
        $classIndex = array_pop($state['classStack']);
        $state['classes'][$classIndex]['end'] = $line;
    }

    $state['depth']--;
}

/**
 * Завершает метод без тела (интерфейс, абстрактный).
 *
 * @param array<string, mixed> $state Состояние разбора.
 * @param int $line Номер строки.
 *
 * @return void
 */
function finishMethod(array &$state, int $line): void
{
    $method = $state['pendingMethod'];
    $method['end'] = $line;
    addMethod($state, $method);
    $state['pendingMethod'] = null;
}

/**
 * Добавляет метод к классу или в список функций.
 *
 * @param array<string, mixed> $state Состояние разбора.
 * @param array<string, mixed> $method Метод.
 *
 * @return void
 */
function addMethod(array &$state, array $method): void
{
    $owner = $method['owner'];
    unset($method['owner'], $method['bodyDepth']);
    if ($owner === null) {
        $state['functions'][] = $method;

        return;
    }

    $state['classes'][$owner]['methods'][] = $method;
}

/**
 * Находит предыдущий значимый токен.
 *
 * @param array<int, mixed> $tokens Все токены файла.
 * @param int $index Позиция токена.
 *
 * @return array{0: int|null, 1: string, 2: int}|null Токен.
 */
function previousSignificant(array $tokens, int $index): ?array
{
    for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
        $token = $tokens[$cursor];
        $id = is_array($token) ? $token[0] : null;
        if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return [$id, is_array($token) ? $token[1] : $token, $cursor];
    }

    return null;
}

/**
 * Находит следующий значимый токен.
 *
 * @param array<int, mixed> $tokens Все токены файла.
 * @param int $index Позиция токена.
 *
 * @return array{0: int|null, 1: string, 2: int}|null Токен.
 */
function nextSignificant(array $tokens, int $index): ?array
{
    for ($cursor = $index + 1, $total = count($tokens); $cursor < $total; $cursor++) {
        $token = $tokens[$cursor];
        $id = is_array($token) ? $token[0] : null;
        if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return [$id, is_array($token) ? $token[1] : $token, $cursor];
    }

    return null;
}

/**
 * Определяет видимость метода.
 *
 * @param array<int, mixed> $tokens Все токены файла.
 * @param int $index Позиция T_FUNCTION.
 *
 * @return string Видимость.
 */
function methodVisibility(array $tokens, int $index): string
{
    for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
        $token = $tokens[$cursor];
        $id = is_array($token) ? $token[0] : null;
        if ($id === T_PUBLIC) {
            return 'public';
        }

        if ($id === T_PROTECTED) {
            return 'protected';
        }

        if ($id === T_PRIVATE) {
            return 'private';
        }

        $skipped = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_STATIC, T_FINAL, T_ABSTRACT, T_READONLY];
        if (!in_array($id, $skipped, true)) {
            break;
        }
    }

    return 'public';
}

/**
 * Определяет вид объявления по токену.
 *
 * @param int $id Идентификатор токена.
 *
 * @return string Вид.
 */
function kindOfToken(int $id): string
{
    return match ($id) {
        T_INTERFACE => 'interface',
        T_TRAIT => 'trait',
        T_ENUM => 'enum',
        default => 'class',
    };
}

/**
 * Считает строки и признаки методов.
 *
 * @param array<int, array<string, mixed>> $methods Методы.
 *
 * @return array<int, array<string, mixed>> Методы с метриками.
 */
function finalizeMethods(array $methods): array
{
    $result = [];
    foreach ($methods as $method) {
        $method['lines'] = $method['end'] - $method['start'] + 1;
        $method['warning'] = $method['lines'] > BIG_METHOD_LINES;
        $result[] = $method;
    }

    return $result;
}

/**
 * Считает метрики классов.
 *
 * @param array<int, array<string, mixed>> $classes Классы.
 *
 * @return array<int, array<string, mixed>> Классы с метриками.
 */
function finalizeClasses(array $classes): array
{
    $result = [];
    foreach ($classes as $class) {
        $methods = finalizeMethods($class['methods']);
        $class['methods'] = $methods;
        $class['lines'] = $class['end'] - $class['start'] + 1;
        $class['public'] = count(array_filter($methods, static fn (array $m): bool => $m['visibility'] === 'public'));
        $class['protected'] = count(array_filter(
            $methods,
            static fn (array $m): bool => $m['visibility'] === 'protected',
        ));
        $class['warnings'] = classWarnings($class);
        unset($class['anonymous'], $class['bodyDepth']);
        $result[] = $class;
    }

    return $result;
}

/**
 * Собирает предупреждения по размеру класса.
 *
 * @param array<string, mixed> $class Класс.
 *
 * @return array<int, string> Предупреждения.
 */
function classWarnings(array $class): array
{
    $warnings = [];
    if ($class['lines'] > 500) {
        $warnings[] = '>500 строк';
    }

    if ($class['public'] > 10) {
        $warnings[] = '>10 публичных';
    }

    if ($class['protected'] > 5) {
        $warnings[] = '>5 защищённых';
    }

    return $warnings;
}

/**
 * Разбирает Vue, TS или JS.
 *
 * @param string $code Текст файла.
 * @param string $extension Расширение.
 *
 * @return array<string, array<int, array<string, mixed>>> Блоки и объявления.
 */
function parseFrontend(string $code, string $extension): array
{
    $lines = explode("\n", $code);
    $blocks = $extension === 'vue' ? parseBlocks($lines) : [];
    $declarations = [];
    foreach ($blocks as $block) {
        if ($block['tag'] !== 'script') {
            continue;
        }

        $found = scanDeclarations($lines, $block['start'] + 1, $block['end'] - 1);
        $declarations = array_merge($declarations, $found);
    }

    if ($extension !== 'vue') {
        $declarations = scanDeclarations($lines, 1, count($lines));
    }

    return ['blocks' => $blocks, 'declarations' => $declarations];
}

/**
 * Находит блоки SFC.
 *
 * @param array<int, string> $lines Строки файла.
 *
 * @return array<int, array<string, mixed>> Блоки.
 */
function parseBlocks(array $lines): array
{
    $blocks = [];
    $open = null;
    foreach ($lines as $index => $line) {
        $number = $index + 1;
        if ($open === null && preg_match('/^<(template|script|style)\b([^>]*)>/', $line, $match) === 1) {
            $open = ['tag' => $match[1], 'attrs' => trim($match[2]), 'start' => $number, 'end' => $number];

            continue;
        }

        if ($open !== null && preg_match('/^<\/' . $open['tag'] . '\s*>/', $line) === 1) {
            $open['end'] = $number;
            $blocks[] = $open;
            $open = null;
        }
    }

    return $blocks;
}

/**
 * Находит объявления верхнего уровня.
 *
 * @param array<int, string> $lines Строки файла.
 * @param int $from Первая строка.
 * @param int $to Последняя строка.
 *
 * @return array<int, array<string, mixed>> Объявления.
 */
function scanDeclarations(array $lines, int $from, int $to): array
{
    $found = [];
    for ($number = $from; $number <= $to; $number++) {
        $declaration = matchDeclaration($lines[$number - 1] ?? '');
        if ($declaration !== null) {
            $found[] = ['line' => $number, 'kind' => $declaration[0], 'name' => $declaration[1]];
        }
    }

    $count = count($found);
    for ($index = 0; $index < $count; $index++) {
        $end = $index + 1 < $count ? $found[$index + 1]['line'] - 1 : $to;
        $found[$index]['start'] = $found[$index]['line'];
        $found[$index]['end'] = $end;
        $found[$index]['lines'] = $end - $found[$index]['line'] + 1;
        $found[$index]['warning'] = $found[$index]['lines'] > BIG_DECLARATION_LINES;
        unset($found[$index]['line']);
    }

    return $found;
}

/**
 * Распознаёт объявление верхнего уровня в строке.
 *
 * @param string $line Строка.
 *
 * @return array{0: string, 1: string}|null Вид и имя.
 */
function matchDeclaration(string $line): ?array
{
    $patterns = [
        'function' => '/^(?:export\s+)?(?:default\s+)?(?:async\s+)?function\s+([A-Za-z_$][\w$]*)/',
        'const' => '/^(?:export\s+)?(?:const|let|var)\s+([A-Za-z_$][\w$]*)/',
        'type' => '/^(?:export\s+)?(?:interface|type|enum)\s+([A-Za-z_$][\w$]*)/',
        'hook' => '/^(watch|watchEffect|onMounted|onUnmounted|onBeforeUnmount)\s*\(/',
    ];

    foreach ($patterns as $kind => $pattern) {
        if (preg_match($pattern, $line, $match) === 1) {
            return [$kind, $match[1]];
        }
    }

    return null;
}

/**
 * Рендерит карту файла.
 *
 * @param array<string, mixed> $file Карта файла.
 * @param bool $bigOnly Только длинные методы и объявления.
 *
 * @return string Текст.
 */
function renderResult(array $file, bool $bigOnly): string
{
    $lines = [headerLine($file)];
    if ($file['eligible']) {
        $lines[] = '  можно читать целиком';

        return implode("\n", $lines);
    }

    $lines[] = '  целиком не читать: карта ниже; диапазон — sed -n \'A,Bp\' ' . $file['path'];
    if ($file['extension'] === 'md') {
        $lines[] = '  для markdown: сначала заголовки (list_doc_headings), потом раздел';
    }

    $lines = array_merge($lines, renderBlocks($file['blocks']), renderClasses($file['classes'], $bigOnly));
    $lines = array_merge($lines, renderMethods($file['functions'], '  ', $bigOnly));
    $lines = array_merge($lines, renderDeclarations($file['declarations'], $bigOnly));

    return implode("\n", $lines);
}

/**
 * Собирает заголовок файла.
 *
 * @param array<string, mixed> $file Карта файла.
 *
 * @return string Заголовок.
 */
function headerLine(array $file): string
{
    $members = countMembers($file);
    $header = sprintf(
        '%s — %d %s, %s',
        $file['path'],
        $file['lines'],
        plural($file['lines'], 'строка', 'строки', 'строк'),
        formatBytes($file['bytes']),
    );

    if ($members > 0) {
        $header .= in_array($file['extension'], FRONTEND_EXTENSIONS, true)
            ? sprintf(', %d %s', $members, plural($members, 'объявление', 'объявления', 'объявлений'))
            : sprintf(', %d %s', $members, plural($members, 'метод', 'метода', 'методов'));
    }

    return $header;
}

/**
 * Считает элементы карты.
 *
 * @param array<string, mixed> $file Карта файла.
 *
 * @return int Число элементов.
 */
function countMembers(array $file): int
{
    $members = count($file['functions']) + count($file['declarations']);
    foreach ($file['classes'] as $class) {
        $members += count($class['methods']);
    }

    return $members;
}

/**
 * Рендерит блоки SFC.
 *
 * @param array<int, array<string, mixed>> $blocks Блоки.
 *
 * @return array<int, string> Строки.
 */
function renderBlocks(array $blocks): array
{
    if ($blocks === []) {
        return [];
    }

    $lines = ['  блоки:'];
    foreach ($blocks as $block) {
        $name = '<' . $block['tag'] . ($block['attrs'] !== '' ? ' ' . $block['attrs'] : '') . '>';
        $lines[] = sprintf('    %-34s L %d-%d', $name, $block['start'], $block['end']);
    }

    return $lines;
}

/**
 * Рендерит классы и их методы.
 *
 * @param array<int, array<string, mixed>> $classes Классы.
 * @param bool $bigOnly Только длинные методы.
 *
 * @return array<int, string> Строки.
 */
function renderClasses(array $classes, bool $bigOnly): array
{
    $lines = [];
    foreach ($classes as $class) {
        $lines[] = sprintf(
            '  %s %s  L %d-%d, %d %s (public %d, protected %d)%s',
            $class['kind'],
            $class['name'],
            $class['start'],
            $class['end'],
            count($class['methods']),
            plural(count($class['methods']), 'метод', 'метода', 'методов'),
            $class['public'],
            $class['protected'],
            $class['warnings'] !== [] ? '  ⚠ ' . implode(', ', $class['warnings']) : '',
        );
        $lines = array_merge($lines, renderMethods($class['methods'], '    ', $bigOnly));
    }

    return $lines;
}

/**
 * Рендерит методы.
 *
 * @param array<int, array<string, mixed>> $methods Методы.
 * @param string $indent Отступ.
 * @param bool $bigOnly Только длинные.
 *
 * @return array<int, string> Строки.
 */
function renderMethods(array $methods, string $indent, bool $bigOnly): array
{
    $shown = [];
    foreach ($methods as $method) {
        if ($bigOnly && !$method['warning']) {
            continue;
        }

        $shown[] = sprintf(
            '%sL %5d-%5d  %-9s %-34s %4d%s',
            $indent,
            $method['start'],
            $method['end'],
            $method['visibility'] ?? $method['kind'] ?? 'function',
            $method['name'],
            $method['lines'],
            $method['warning'] ? '  ⚠' : '',
        );
    }

    if ($shown === []) {
        return [];
    }

    return $shown;
}

/**
 * Рендерит объявления Vue и TS.
 *
 * @param array<int, array<string, mixed>> $declarations Объявления.
 * @param bool $bigOnly Только длинные.
 *
 * @return array<int, string> Строки.
 */
function renderDeclarations(array $declarations, bool $bigOnly): array
{
    if ($declarations === []) {
        return [];
    }

    $lines = ['  объявления (' . count($declarations) . '):'];

    return array_merge($lines, renderMethods($declarations, '    ', $bigOnly));
}

/**
 * Форматирует размер.
 *
 * @param int $bytes Байты.
 *
 * @return string Размер.
 */
function formatBytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' Б';
    }

    if ($bytes < 1024 * 1024) {
        return max(1, (int) round($bytes / 1024)) . ' КБ';
    }

    return round($bytes / 1024 / 1024, 1) . ' МБ';
}

/**
 * Склоняет слово по числу.
 *
 * @param int $count Число.
 * @param string $one Форма для одного.
 * @param string $few Форма для двух-четырёх.
 * @param string $many Форма для остальных.
 *
 * @return string Форма.
 */
function plural(int $count, string $one, string $few, string $many): string
{
    $mod100 = $count % 100;
    $mod10 = $count % 10;
    if ($mod100 >= 11 && $mod100 <= 14) {
        return $many;
    }

    if ($mod10 === 1) {
        return $one;
    }

    if ($mod10 >= 2 && $mod10 <= 4) {
        return $few;
    }

    return $many;
}

/**
 * Печатает справку.
 *
 * @return void
 */
function printUsage(): void
{
    $usage = <<<'TEXT'
outline — карта файла кода с диапазонами строк.

Запуск: php tools/outline/show.php [--big] [--json] <файл>...

  --big   только методы длиннее 30 строк и объявления длиннее 40 строк
  --json  машинный формат

Файл до 300 строк и 60 КБ не разбирается: карта не нужна, читайте целиком.
PHP — через token_get_all; Vue/TS/JS — блоки SFC и объявления верхнего уровня.
Границы объявлений во фронтенде приблизительны: от объявления до следующего.

Примеры:
  php tools/outline/show.php modules/Roleplay/Game/Service/GameStrikes.php
  php tools/outline/show.php --big ../../draft-front_1.2ds/src/modules/Roleplay/Game/Component/HitLaunchDialog.vue
TEXT;
    fwrite(STDOUT, $usage . "\n");
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
    fwrite(STDERR, $message . "\n");
    exit(1);
}
