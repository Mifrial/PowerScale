<?php

declare(strict_types=1);

namespace Mifrial\Tools\Duplicates;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Ищет дублированные участки PHP-кода (CPD-подобный алгоритм).
 *
 * Нормализация консервативная: пробелы и комментарии отбрасываются,
 * переменные и литералы приводятся к одному маркеру, имена методов и типов
 * сохраняются. Поэтому находятся клоны с переименованными переменными и
 * переформатированием, а «похожий по смыслу» код — нет. Это осознанно:
 * цена ложного срабатывания выше цены пропуска, а Type-3 клоны всё равно
 * требуют человека.
 *
 * Алгоритм: rolling hash по k-граммам нормализованных токенов (O(n)),
 * подтверждение точным сравнением, максимальное расширение вперёд,
 * группировка одинакового содержимого и отбрасывание вложенных участков.
 */
final class Scanner
{
    private const HashBase = 131;

    private const HashModulus = 2147483647;

    /** Предел вхождений одной k-граммы в корзине: защита от квадратичности. */
    private const MaximumOccurrencesPerGram = 64;

    /**
     * @param list<string> $excludePaths          Шаблоны fnmatch от корня сканирования.
     * @param list<string> $excludeFolders        Папки, чьи дубли идут только в отчёт.
     * @param list<string> $alwaysIncludeFolders  Папки, чьи дубли всегда в гейте.
     * @param list<array{paths?: list<string>, reason?: string, review?: string}> $allow
     */
    public function __construct(
        private readonly string $scanDir,
        private readonly int $minTokens = 50,
        private readonly int $minLines = 8,
        private readonly array $excludePaths = ['modules/*/tests/*'],
        private readonly array $excludeFolders = ['Dto', 'Table', 'Spec'],
        private readonly array $alwaysIncludeFolders = ['Value'],
        private readonly array $allow = [],
        private readonly ?string $onlyModule = null,
    ) {
    }

    /**
     * @param array<string, mixed> $config Содержимое tools/duplicates/config.json.
     */
    public static function fromConfig(string $scanDir, array $config): self
    {
        $gate = is_array($config['gate'] ?? null) ? $config['gate'] : [];

        return new self(
            $scanDir,
            (int) ($config['minTokens'] ?? 50),
            (int) ($config['minLines'] ?? 8),
            self::readStringList($config['excludePaths'] ?? []),
            self::readStringList($gate['excludeFolders'] ?? []),
            self::readStringList($gate['alwaysIncludeFolders'] ?? []),
            self::readAllowList($config['allow'] ?? []),
            isset($config['onlyModule']) && is_string($config['onlyModule']) && $config['onlyModule'] !== ''
                ? $config['onlyModule']
                : null,
        );
    }

    /**
     * @return array{clusters: list<Cluster>, stats: array<string, mixed>}
     */
    public function scan(): array
    {
        $state = $this->tokenizeAll();
        if ($state['tokens'] === []) {
            return ['clusters' => [], 'stats' => $this->computeStats([], $state['codeLines'], count($state['paths']))];
        }

        $groups = $this->findGroups($state);
        $groups = $this->dropCovered($groups);
        $clusters = $this->buildClusters($groups);

        usort($clusters, static function (Cluster $left, Cluster $right): int {
            return [$right->lines, $left->occurrences[0]->path, $left->occurrences[0]->line]
                <=> [$left->lines, $right->occurrences[0]->path, $right->occurrences[0]->line];
        });

        $clusters = $this->filterClusters($clusters);

        return [
            'clusters' => $clusters,
            'stats' => $this->computeStats($clusters, $state['codeLines'], count($state['paths'])),
        ];
    }

    /**
     * @return list<string> Абсолютные пути, отсортированные.
     */
    private function collectFiles(): array
    {
        if (!is_dir($this->scanDir)) {
            throw new RuntimeException(sprintf('Scan directory does not exist: %s', $this->scanDir));
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->scanDir, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if (!$entry->isFile() || $entry->getExtension() !== 'php') {
                continue;
            }

            $absolutePath = $entry->getPathname();
            if ($this->isExcluded($this->relativePath($absolutePath))) {
                continue;
            }

            $files[] = $absolutePath;
        }

        sort($files);

        return $files;
    }

    private function isExcluded(string $relativePath): bool
    {
        foreach ($this->excludePaths as $pattern) {
            if (fnmatch($pattern, $relativePath)) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $absolutePath): string
    {
        $scanDir = rtrim($this->scanDir, '/');
        $prefix = $scanDir . '/';

        if (str_starts_with($absolutePath, $prefix)) {
            return basename($scanDir) . '/' . substr($absolutePath, strlen($prefix));
        }

        return $absolutePath;
    }

    /**
     * @return array{
     *     tokens: list<int>,
     *     lines: list<int>,
     *     fileStarts: list<int>,
     *     fileEnds: list<int>,
     *     paths: list<string>,
     *     codeLines: int
     * }
     */
    private function tokenizeAll(): array
    {
        $tokens = [];
        $lines = [];
        $fileStarts = [];
        $fileEnds = [];
        $paths = [];
        $codeLines = 0;
        $idByKey = [];

        foreach ($this->collectFiles() as $absolutePath) {
            $source = file_get_contents($absolutePath);
            if ($source === false) {
                throw new RuntimeException(sprintf('Cannot read %s', $absolutePath));
            }

            $relativePath = $this->relativePath($absolutePath);

            $fileStart = count($tokens);
            $currentLine = 1;
            $lastLine = 1;

            foreach (token_get_all($source) as $token) {
                if (is_array($token)) {
                    $type = $token[0];
                    $text = $token[1];
                    $currentLine = $token[2];

                    if ($type === T_WHITESPACE || $type === T_COMMENT || $type === T_DOC_COMMENT) {
                        $currentLine += substr_count($text, "\n");
                        continue;
                    }

                    $key = $this->tokenKey($type, $text);
                } else {
                    $key = 'char:' . $token;
                }

                $id = $idByKey[$key] ?? null;
                if ($id === null) {
                    $id = count($idByKey) + 1;
                    $idByKey[$key] = $id;
                }

                $tokens[] = $id;
                $lines[] = $currentLine;
                $lastLine = $currentLine;

                if (is_array($token)) {
                    $currentLine += substr_count($token[1], "\n");
                } elseif ($token === "\n") {
                    ++$currentLine;
                }
            }

            if (count($tokens) === $fileStart) {
                continue;
            }

            $fileStarts[] = $fileStart;
            $fileEnds[] = count($tokens);
            $paths[$fileStart] = $relativePath;
            $codeLines += max(1, $lastLine);
        }
        return [
            'tokens' => $tokens,
            'lines' => $lines,
            'fileStarts' => $fileStarts,
            'fileEnds' => $fileEnds,
            'paths' => $paths,
            'codeLines' => $codeLines,
        ];
    }

    private function tokenKey(int $type, string $text): string
    {
        if ($type === T_VARIABLE) {
            return 'variable';
        }

        if (
            $type === T_STRING
            || $type === T_NAME_QUALIFIED
            || $type === T_NAME_FULLY_QUALIFIED
            || $type === T_NAME_RELATIVE
        ) {
            return 'name:' . $text;
        }

        if (
            $type === T_CONSTANT_ENCAPSED_STRING
            || $type === T_LNUMBER
            || $type === T_DNUMBER
            || $type === T_ENCAPSED_AND_WHITESPACE
        ) {
            return 'literal';
        }

        return 'type:' . $type;
    }

    /**
     * @param array{tokens: list<int>, lines: list<int>, fileStarts: list<int>, fileEnds: list<int>, paths: list<string>, codeLines: int} $state
     *
     * @return list<array{length: int, spots: array<string, array{path: string, line: int, endLine: int}>}>
     */
    private function findGroups(array $state): array
    {
        $tokens = $state['tokens'];
        $lines = $state['lines'];
        $tokenCount = count($tokens);
        $gram = $this->minTokens;

        if ($gram < 1 || $tokenCount < $gram) {
            return [];
        }

        $buckets = [];
        $hash = 0;
        $power = 1;
        for ($i = 0; $i < $gram - 1; ++$i) {
            $power = ($power * self::HashBase) % self::HashModulus;
        }

        for ($i = 0; $i < $tokenCount; ++$i) {
            $hash = ($hash * self::HashBase + $tokens[$i]) % self::HashModulus;

            if ($i < $gram - 1) {
                continue;
            }

            $start = $i - $gram + 1;
            if (count($buckets[$hash] ?? []) < self::MaximumOccurrencesPerGram) {
                $buckets[$hash][] = $start;
            }

            $hash = ($hash - $tokens[$start] * $power) % self::HashModulus;
            if ($hash < 0) {
                $hash += self::HashModulus;
            }
        }

        $groups = [];

        foreach ($buckets as $starts) {
            $occurrenceCount = count($starts);
            if ($occurrenceCount < 2) {
                continue;
            }

            for ($left = 0; $left < $occurrenceCount; ++$left) {
                for ($right = $left + 1; $right < $occurrenceCount; ++$right) {
                    $first = $starts[$left];
                    $second = $starts[$right];

                    if (!$this->sameTokens($tokens, $first, $second, $gram)) {
                        continue;
                    }

                    $length = $this->extendBlock($state, $first, $second, $gram);
                    if (max($this->lineSpan($lines, $first, $length), $this->lineSpan($lines, $second, $length)) < $this->minLines) {
                        continue;
                    }

                    $key = $length . ':' . sha1(implode(',', array_slice($tokens, $first, $length)));
                    $groups[$key] ??= ['length' => $length, 'spots' => []];

                    $firstSpan = $this->spanOf($state, $lines, $first, $length);
                    $secondSpan = $this->spanOf($state, $lines, $second, $length);
                    $groups[$key]['spots'][$firstSpan['path'] . ':' . $firstSpan['line']] = $firstSpan;
                    $groups[$key]['spots'][$secondSpan['path'] . ':' . $secondSpan['line']] = $secondSpan;
                }
            }
        }

        return array_values($groups);
    }

    /**
     * @param list<int> $tokens
     */
    private function sameTokens(array $tokens, int $first, int $second, int $length): bool
    {
        for ($offset = 0; $offset < $length; ++$offset) {
            if ($tokens[$first + $offset] !== $tokens[$second + $offset]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{tokens: list<int>, lines: list<int>, fileStarts: list<int>, fileEnds: list<int>, paths: list<string>, codeLines: int} $state
     */
    private function extendBlock(array $state, int $first, int $second, int $length): int
    {
        $tokens = $state['tokens'];
        $firstEnd = $this->fileEndOf($state, $first);
        $secondEnd = $this->fileEndOf($state, $second);

        while (
            $first + $length < $firstEnd
            && $second + $length < $secondEnd
            && $tokens[$first + $length] === $tokens[$second + $length]
        ) {
            ++$length;
        }

        return $length;
    }

    /**
     * @param array{fileStarts: list<int>, fileEnds: list<int>, paths: list<string>, ...} $state
     */
    private function fileEndOf(array $state, int $tokenIndex): int
    {
        return $state['fileEnds'][$this->locateFile($state, $tokenIndex)];
    }

    /**
     * @param array{tokens: list<int>, fileStarts: list<int>, paths: list<string>, ...} $state
     * @param list<int> $lines
     *
     * @return array{path: string, line: int, endLine: int}
     */
    private function spanOf(array $state, array $lines, int $start, int $length): array
    {
        $fileIndex = $this->locateFile($state, $start);

        return [
            'path' => $state['paths'][$state['fileStarts'][$fileIndex]],
            'line' => $lines[$start],
            'endLine' => $lines[$start + $length - 1],
        ];
    }

    /**
     * Индекс файла в общем потоке токенов (двоичный поиск по началам файлов).
     *
     * @param array{fileStarts: list<int>, ...} $state
     */
    private function locateFile(array $state, int $tokenIndex): int
    {
        $low = 0;
        $high = count($state['fileStarts']) - 1;

        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);
            if ($state['fileStarts'][$middle] <= $tokenIndex) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        return $low;
    }

    /**
     * @param list<int> $lines
     */
    private function lineSpan(array $lines, int $start, int $length): int
    {
        return $lines[$start + $length - 1] - $lines[$start] + 1;
    }

    /**
     * Вложенные участки: группа отбрасывается, если каждое её вхождение
     * целиком покрыто уже принятой группой того же файла.
     *
     * @param list<array{length: int, spots: array<string, array{path: string, line: int, endLine: int}>}> $groups
     *
     * @return list<array{length: int, spots: array<string, array{path: string, line: int, endLine: int}>}>
     */
    private function dropCovered(array $groups): array
    {
        usort($groups, static function (array $left, array $right): int {
            return $right['length'] <=> $left['length'];
        });

        $kept = [];
        /** @var array<string, list<array{line: int, endLine: int}>> $covered */
        $covered = [];

        foreach ($groups as $group) {
            $isCovered = true;

            foreach ($group['spots'] as $spot) {
                if (!$this->isSpotCovered($covered, $spot)) {
                    $isCovered = false;
                    break;
                }
            }

            if ($isCovered) {
                continue;
            }

            $kept[] = $group;
            foreach ($group['spots'] as $spot) {
                $covered[$spot['path']][] = ['line' => $spot['line'], 'endLine' => $spot['endLine']];
            }
        }

        return $kept;
    }

    /**
     * @param array<string, list<array{line: int, endLine: int}>> $covered
     * @param array{path: string, line: int, endLine: int} $spot
     */
    private function isSpotCovered(array $covered, array $spot): bool
    {
        foreach ($covered[$spot['path']] ?? [] as $range) {
            if ($range['line'] <= $spot['line'] && $range['endLine'] >= $spot['endLine']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{length: int, spots: array<string, array{path: string, line: int, endLine: int}>}> $groups
     *
     * @return list<Cluster>
     */
    private function buildClusters(array $groups): array
    {
        $clusters = [];

        foreach ($groups as $group) {
            $occurrences = [];
            foreach ($group['spots'] as $spot) {
                $occurrences[] = new Occurrence($spot['path'], $spot['line'], $spot['endLine']);
            }

            usort($occurrences, static function (Occurrence $left, Occurrence $right): int {
                return [$left->path, $left->line] <=> [$right->path, $right->line];
            });

            $lines = $occurrences[0]->endLine - $occurrences[0]->line + 1;
            foreach ($occurrences as $occurrence) {
                $lines = min($lines, $occurrence->endLine - $occurrence->line + 1);
            }

            [$kind, $reason] = $this->classifyCluster($occurrences);

            $clusters[] = new Cluster($group['length'], $lines, $occurrences, $kind, $reason);
        }

        return $clusters;
    }

    /**
     * @param list<Occurrence> $occurrences
     *
     * @return array{0: string, 1: string|null}
     */
    private function classifyCluster(array $occurrences): array
    {
        $paths = array_map(static fn(Occurrence $occurrence): string => $occurrence->path, $occurrences);

        $reason = $this->matchAllowRule($paths);
        if ($reason !== null) {
            return [Cluster::KIND_ALLOWED, $reason];
        }

        $directories = array_map(fn(string $path): array => $this->directoriesOf($path), $paths);

        foreach ($directories as $segments) {
            if (array_intersect($segments, $this->alwaysIncludeFolders) !== []) {
                return [Cluster::KIND_GATE, null];
            }
        }

        $onlyExcludedFolders = true;
        foreach ($directories as $segments) {
            if (array_intersect($segments, $this->excludeFolders) === []) {
                $onlyExcludedFolders = false;
                break;
            }
        }

        if ($onlyExcludedFolders) {
            return [Cluster::KIND_BOILERPLATE, null];
        }

        $modules = array_unique(array_map(fn(string $path): string => $this->moduleOf($path), $paths));
        if (count($modules) === 1) {
            return [Cluster::KIND_GATE, null];
        }

        return [Cluster::KIND_CROSS_MODULE, null];
    }

    /**
     * @param list<string> $paths
     */
    private function matchAllowRule(array $paths): ?string
    {
        foreach ($this->allow as $rule) {
            $patterns = self::readStringList($rule['paths'] ?? []);
            if ($patterns === []) {
                continue;
            }

            $matched = true;
            foreach ($paths as $path) {
                if (!$this->matchesAny($path, $patterns)) {
                    $matched = false;
                    break;
                }
            }

            if (!$matched) {
                continue;
            }

            $reason = trim((string) ($rule['reason'] ?? ''));
            $review = trim((string) ($rule['review'] ?? ''));

            return $review === '' ? $reason : sprintf('%s (review %s)', $reason, $review);
        }

        return null;
    }

    /**
     * @param list<string> $patterns
     */
    private function matchesAny(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Cluster> $clusters
     *
     * @return list<Cluster>
     */
    private function filterClusters(array $clusters): array
    {
        if ($this->onlyModule === null) {
            return $clusters;
        }

        return array_values(array_filter(
            $clusters,
            function (Cluster $cluster): bool {
                foreach ($cluster->occurrences as $occurrence) {
                    if ($this->matchesModule($occurrence->path)) {
                        return true;
                    }
                }

                return false;
            },
        ));
    }

    private function matchesModule(string $relativePath): bool
    {
        if ($this->onlyModule === null) {
            return true;
        }

        $segments = explode('/', $this->stripScanPrefix($relativePath));
        $needle = strtolower($this->onlyModule);

        return strtolower($segments[1] ?? '') === $needle
            || strtolower(($segments[0] ?? '') . '/' . ($segments[1] ?? '')) === $needle;
    }

    private function moduleOf(string $relativePath): string
    {
        $segments = explode('/', $this->stripScanPrefix($relativePath));

        return count($segments) >= 2 ? $segments[0] . '/' . $segments[1] : '';
    }

    /**
     * Каталоги внутри модуля: `modules/Core/User/Dto/Value/X.php` → `['Dto', 'Value']`.
     *
     * @return list<string>
     */
    private function directoriesOf(string $relativePath): array
    {
        $segments = explode('/', $this->stripScanPrefix($relativePath));

        return array_values(array_slice($segments, 2, -1));
    }

    private function stripScanPrefix(string $relativePath): string
    {
        $prefix = basename(rtrim($this->scanDir, '/')) . '/';

        if (str_starts_with($relativePath, $prefix)) {
            return substr($relativePath, strlen($prefix));
        }

        return $relativePath;
    }

    /**
     * @param list<Cluster> $clusters
     *
     * @return array{
     *     clusters: int,
     *     lines: int,
     *     gateClusters: int,
     *     gateLines: int,
     *     codeLines: int,
     *     files: int,
     *     kinds: array<string, int>,
     *     byModule: array<string, int>
     * }
     */
    private function computeStats(array $clusters, int $codeLines, int $files): array
    {
        $kinds = [
            Cluster::KIND_GATE => 0,
            Cluster::KIND_BOILERPLATE => 0,
            Cluster::KIND_CROSS_MODULE => 0,
            Cluster::KIND_ALLOWED => 0,
        ];
        $allLines = [];
        $gateLines = [];
        $byModule = [];

        foreach ($clusters as $cluster) {
            ++$kinds[$cluster->kind];

            foreach ($cluster->occurrences as $occurrence) {
                for ($line = $occurrence->line; $line <= $occurrence->endLine; ++$line) {
                    $allLines[$occurrence->path . ':' . $line] = true;
                    if ($cluster->kind === Cluster::KIND_GATE) {
                        $gateLines[$occurrence->path . ':' . $line] = true;
                    }
                }
            }
        }

        foreach (array_keys($gateLines) as $key) {
            $path = strstr((string) $key, ':', true);
            if ($path === false) {
                continue;
            }

            $module = $this->moduleOf($path);
            $byModule[$module] = ($byModule[$module] ?? 0) + 1;
        }

        arsort($byModule);

        return [
            'clusters' => count($clusters),
            'lines' => count($allLines),
            'gateClusters' => $kinds[Cluster::KIND_GATE],
            'gateLines' => count($gateLines),
            'codeLines' => $codeLines,
            'files' => $files,
            'kinds' => $kinds,
            'byModule' => $byModule,
        ];
    }

    /**
     * @return list<string>
     */
    private static function readStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @return list<array{paths: list<string>, reason: string, review: string}>
     */
    private static function readAllowList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $result[] = [
                'paths' => self::readStringList($item['paths'] ?? []),
                'reason' => (string) ($item['reason'] ?? ''),
                'review' => (string) ($item['review'] ?? ''),
            ];
        }

        return $result;
    }
}
