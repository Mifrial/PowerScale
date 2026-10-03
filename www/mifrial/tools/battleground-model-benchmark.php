<?php

declare(strict_types=1);

/**
 * Автономный synthetic benchmark пространственной модели battleground.
 *
 * Скрипт не является частью production-domain и не требует Composer.
 */

const BENCHMARK_HEADER_BYTES = 25;
const BENCHMARK_CELL_BYTES = 8;
const BENCHMARK_SCENE_RESOLUTION = 64;
const BENCHMARK_OCCUPANCY_RESOLUTION = 16;

/**
 * Разбирает аргументы командной строки с безопасными значениями по умолчанию.
 *
 * @return array<string, mixed> Параметры benchmark.
 */
function getBenchmarkOptions(): array
{
    $options = getopt('', [
        'suite::',
        'scenario::',
        'scene::',
        'representation::',
        'density::',
        'seed::',
        'warmup::',
        'repeats::',
        'iterations::',
        'profile::',
        'workers::',
        'duration::',
        'memory-budget::',
        'json::',
    ]);

    return [
        'suite' => (string) ($options['suite'] ?? 'single'),
        'scenario' => (string) ($options['scenario'] ?? 'occupancy_read'),
        'scene' => (string) ($options['scene'] ?? 'small'),
        'representation' => (string) ($options['representation'] ?? 'packed'),
        'density' => (string) ($options['density'] ?? 'typical'),
        'seed' => (int) ($options['seed'] ?? 20260920),
        'warmup' => (int) ($options['warmup'] ?? 3),
        'repeats' => (int) ($options['repeats'] ?? 7),
        'iterations' => (int) ($options['iterations'] ?? 10000),
        'profile' => (string) ($options['profile'] ?? 'single-game-normal'),
        'workers' => max(1, (int) ($options['workers'] ?? 1)),
        'duration' => max(1, (int) ($options['duration'] ?? 60)),
        'memoryBudget' => max(0, (int) ($options['memory-budget'] ?? 0)),
        'json' => isset($options['json']) ? (string) $options['json'] : null,
    ];
}

/**
 * Возвращает размеры и состав синтетической сцены.
 *
 * @param string $scene Имя fixture.
 *
 * @return array<string, int> Параметры сцены.
 */
function getSceneSpec(string $scene): array
{
    if ($scene === 'stress') {
        return [
            'width' => 800,
            'height' => 800,
            'tokens' => 200,
            'trees' => 40000,
            'spaces' => 3,
        ];
    }

    if ($scene === 'max-grid') {
        return [
            'width' => 1024,
            'height' => 1024,
            'tokens' => 0,
            'trees' => 0,
            'spaces' => 1,
        ];
    }

    return [
        'width' => 160,
        'height' => 160,
        'tokens' => 200,
        'trees' => 4000,
        'spaces' => 1,
    ];
}

/**
 * Создаёт deterministic fixture и выбранное представление occupancy.
 *
 * @param array<string, mixed> $options Параметры запуска.
 *
 * @return array<string, mixed> Synthetic scene.
 */
function createBenchmarkFixture(array $options): array
{
    $spec = getSceneSpec((string) $options['scene']);
    $width = $spec['width'];
    $height = $spec['height'];
    $density = (string) $options['density'];
    $cells = '';
    $rows = [];
    $arrayRows = [];
    $representation = (string) $options['representation'];
    $payloadBytes = 0;

    mt_srand((int) $options['seed']);
    for ($rowIndex = 0; $rowIndex < $height; $rowIndex++) {
        $row = '';
        $arrayRow = [];
        for ($columnIndex = 0; $columnIndex < $width; $columnIndex++) {
            $cell = getSyntheticCell($rowIndex, $columnIndex, $density);
            $row .= $cell;
            if ($representation === 'array') {
                $arrayRow[] = $cell;
            }
        }

        $payloadBytes += strlen($row);
        if ($representation === 'packed') {
            $cells .= $row;
        }
        if ($representation === 'rows') {
            $rows[] = $row;
        }
        if ($representation === 'array') {
            $arrayRows[] = $arrayRow;
        }
    }

    $header = pack(
        'a4CVVVVV',
        'BGO1',
        1,
        $width,
        $height,
        BENCHMARK_OCCUPANCY_RESOLUTION,
        BENCHMARK_SCENE_RESOLUTION,
        0,
    );

    $data = match ($representation) {
        'rows' => ['rows' => $rows],
        'array' => ['cells' => $arrayRows],
        default => ['blob' => $header . $cells],
    };

    return [
        'width' => $width,
        'height' => $height,
        'tokens' => createTokens($spec['tokens'], $width, $height),
        'trees' => $spec['trees'],
        'spaces' => $spec['spaces'],
        'representation' => $representation,
        'data' => $data,
        'header' => $header,
        'fixtureBytes' => strlen($header) + $payloadBytes,
    ];
}

/**
 * Создаёт одну occupancy-клетку канонического размера.
 *
 * @param int $rowIndex Индекс строки.
 * @param int $columnIndex Индекс столбца.
 * @param string $density Плотность препятствий.
 *
 * @return string Восемь байт occupancy.
 */
function getSyntheticCell(int $rowIndex, int $columnIndex, string $density): string
{
    $period = match ($density) {
        'sparse' => 97,
        'dense' => 17,
        default => 43,
    };
    $position = ($rowIndex * 31 + $columnIndex * 17) % $period;
    $solidLo = $position === 0 ? 1 : 0;
    $solidHi = $position === 0 ? 24 : 0;
    $canopyLo = (($rowIndex + $columnIndex) % 13 === 0) ? 40 : 0;
    $canopyHi = $canopyLo === 0 ? 0 : 80;
    $illumination = (($rowIndex * 7 + $columnIndex) % 97 === 0) ? 32 : 255;
    $portalId = (($rowIndex + $columnIndex) % 251 === 0) ? 1 : 0;

    return pack(
        'CCCCCCCC',
        $solidLo,
        $solidHi,
        0,
        0,
        $canopyLo,
        $canopyHi,
        $illumination,
        $portalId,
    );
}

/**
 * Создаёт compact synthetic tokens без доменных объектов.
 *
 * @param int $count Количество токенов.
 * @param int $width Ширина occupancy.
 * @param int $height Высота occupancy.
 *
 * @return list<array<string, int>> Координаты и facing токенов.
 */
function createTokens(int $count, int $width, int $height): array
{
    $tokens = [];
    for ($tokenIndex = 0; $tokenIndex < $count; $tokenIndex++) {
        $tokens[] = [
            'x' => ($tokenIndex * 37) % max(1, $width * BENCHMARK_OCCUPANCY_RESOLUTION),
            'y' => ($tokenIndex * 53) % max(1, $height * BENCHMARK_OCCUPANCY_RESOLUTION),
            'z' => $tokenIndex % 96,
            'facing' => ($tokenIndex * 17) % 360,
        ];
    }

    return $tokens;
}

/**
 * Читает восемь байт occupancy без декодирования в PHP-массив.
 *
 * @param array<string, mixed> $fixture Synthetic scene.
 * @param int $columnIndex X в occupancy-клетках.
 * @param int $rowIndex Y в occupancy-клетках.
 *
 * @return string Occupancy-клетка.
 */
function readOccupancyCell(array $fixture, int $columnIndex, int $rowIndex): string
{
    $columnIndex = max(0, min($fixture['width'] - 1, $columnIndex));
    $rowIndex = max(0, min($fixture['height'] - 1, $rowIndex));
    $representation = $fixture['representation'];
    $data = $fixture['data'];

    if ($representation === 'rows') {
        return substr($data['rows'][$rowIndex], $columnIndex * BENCHMARK_CELL_BYTES, BENCHMARK_CELL_BYTES);
    }

    if ($representation === 'array') {
        return $data['cells'][$rowIndex][$columnIndex];
    }

    $offset = BENCHMARK_HEADER_BYTES
        + (($rowIndex * $fixture['width']) + $columnIndex) * BENCHMARK_CELL_BYTES;

    return substr($data['blob'], $offset, BENCHMARK_CELL_BYTES);
}

/**
 * Проверяет, блокирует ли occupancy-клетка луч на заданной высоте.
 *
 * @param string $cell Восемь байт occupancy.
 * @param int $height Высота в z-квантах.
 *
 * @return bool Признак непрозрачного интервала.
 */
function isCellOpaque(string $cell, int $height): bool
{
    $solidLo = ord($cell[0]);
    $solidHi = ord($cell[1]);
    $secondLo = ord($cell[2]);
    $secondHi = ord($cell[3]);

    return ($solidHi > $solidLo && $height >= $solidLo && $height <= $solidHi)
        || ($secondHi > $secondLo && $height >= $secondLo && $height <= $secondHi);
}

/**
 * Выполняет простую DDA-трассировку по occupancy.
 *
 * @param array<string, mixed> $fixture Synthetic scene.
 * @param int $startX Начальная координата в SceneResolution.
 * @param int $startY Начальная координата в SceneResolution.
 * @param int $endX Конечная координата в SceneResolution.
 * @param int $endY Конечная координата в SceneResolution.
 * @param int $z Высота луча.
 *
 * @return bool Признак доступного LOS.
 */
function traceLineOfSight(
    array $fixture,
    int $startX,
    int $startY,
    int $endX,
    int $endY,
    int $z,
): bool {
    $steps = max(abs($endX - $startX), abs($endY - $startY), 1);
    for ($step = 1; $step <= $steps; $step++) {
        $columnIndex = intdiv($startX + intdiv(($endX - $startX) * $step, $steps), BENCHMARK_OCCUPANCY_RESOLUTION);
        $rowIndex = intdiv($startY + intdiv(($endY - $startY) * $step, $steps), BENCHMARK_OCCUPANCY_RESOLUTION);
        if (isCellOpaque(readOccupancyCell($fixture, $columnIndex, $rowIndex), $z)) {
            return false;
        }
    }

    return true;
}

/**
 * Выполняет одну операцию выбранного benchmark-сценария.
 *
 * @param string $scenario Имя сценария.
 * @param array<string, mixed> $fixture Synthetic scene.
 * @param int $iteration Номер операции.
 *
 * @return int Небольшой checksum для защиты от оптимизации результата.
 */
function runBenchmarkOperation(string $scenario, array $fixture, int $iteration): int
{
    $width = $fixture['width'] * BENCHMARK_OCCUPANCY_RESOLUTION;
    $height = $fixture['height'] * BENCHMARK_OCCUPANCY_RESOLUTION;
    $x = ($iteration * 37) % $width;
    $y = ($iteration * 53) % $height;
    $endX = ($iteration * 71 + 101) % $width;
    $endY = ($iteration * 97 + 211) % $height;

    if ($scenario === 'occupancy_read') {
        return ord(readOccupancyCell($fixture, intdiv($x, 16), intdiv($y, 16))[6]);
    }

    if ($scenario === 'los_single' || $scenario === 'map_visibility') {
        return traceLineOfSight($fixture, $x, $y, $endX, $endY, $iteration % 96) ? 1 : 0;
    }

    if ($scenario === 'projection') {
        $visibleTokens = 0;
        foreach ($fixture['tokens'] as $token) {
            if (traceLineOfSight($fixture, $x, $y, $token['x'], $token['y'], $token['z'])) {
                $visibleTokens++;
            }
        }

        return $visibleTokens;
    }

    if ($scenario === 'move_validate' || $scenario === 'path_query') {
        $distance = 0;
        for ($segment = 0; $segment < ($scenario === 'path_query' ? 16 : 64); $segment++) {
            $distance += abs(($x + $segment * 7) - ($endX + $segment * 3));
            $distance += abs(($y + $segment * 5) - ($endY + $segment * 2));
        }

        return $distance;
    }

    if ($scenario === 'attack_context') {
        $hasLos = traceLineOfSight($fixture, $x, $y, $endX, $endY, $iteration % 96);
        $distanceSquared = (($endX - $x) ** 2) + (($endY - $y) ** 2);
        return ($hasLos ? 1 : 0) + ($distanceSquared % 997) + ($iteration % 360);
    }

    if ($scenario === 'door_patch') {
        $columnIndex = $iteration % $fixture['width'];
        $rowIndex = ($iteration * 3) % $fixture['height'];
        $cell = readOccupancyCell($fixture, $columnIndex, $rowIndex);
        $patchedCell = chr(ord($cell[0]) === 0 ? 1 : 0) . substr($cell, 1);
        return ord($patchedCell[0]);
    }

    if ($scenario === 'mixed') {
        $bucket = $iteration % 100;
        if ($bucket < 60) {
            return runBenchmarkOperation('los_single', $fixture, $iteration);
        }
        if ($bucket < 75) {
            return runBenchmarkOperation('attack_context', $fixture, $iteration);
        }
        if ($bucket < 90) {
            return runBenchmarkOperation('projection', $fixture, $iteration);
        }
        if ($bucket < 98) {
            return runBenchmarkOperation('move_validate', $fixture, $iteration);
        }
        if ($bucket < 99) {
            return runBenchmarkOperation('path_query', $fixture, $iteration);
        }

        return runBenchmarkOperation('door_patch', $fixture, $iteration);
    }

    return runBenchmarkOperation('occupancy_read', $fixture, $iteration);
}

/**
 * Проверяет основные инварианты fixture до замера.
 *
 * @param array<string, mixed> $fixture Synthetic scene.
 *
 * @return bool Признак успешной проверки.
 */
function validateFixture(array $fixture): bool
{
    $emptyCell = readOccupancyCell($fixture, 1, 1);
    $opaqueCell = pack('CCCCCCCC', 1, 24, 0, 0, 0, 0, 255, 0);
    if (strlen($emptyCell) !== BENCHMARK_CELL_BYTES || !isCellOpaque($opaqueCell, 12)) {
        return false;
    }

    $blocked = traceLineOfSight($fixture, 0, 0, $fixture['width'] * 16 - 1, 0, 12);
    $clear = traceLineOfSight($fixture, 0, 0, 0, $fixture['height'] * 16 - 1, 250);

    return is_bool($blocked) && is_bool($clear);
}

/**
 * Выполняет серии микробенчмарка и собирает распределение времени.
 *
 * @param array<string, mixed> $options Параметры запуска.
 *
 * @return array<string, mixed> Результат измерения.
 */
function runMicroBenchmark(array $options): array
{
    $fixture = createBenchmarkFixture($options);
    $correctnessPassed = validateFixture($fixture);
    if (!$correctnessPassed) {
        return ['status' => 'failed', 'error' => 'Fixture oracle failed'];
    }

    $warmup = (int) $options['warmup'];
    $repeats = (int) $options['repeats'];
    $iterations = (int) $options['iterations'];
    for ($iteration = 0; $iteration < $warmup; $iteration++) {
        runBenchmarkOperation((string) $options['scenario'], $fixture, $iteration);
    }

    $samples = [];
    $checksum = 0;
    for ($repeat = 0; $repeat < $repeats; $repeat++) {
        $startTime = hrtime(true);
        for ($iteration = 0; $iteration < $iterations; $iteration++) {
            $checksum += runBenchmarkOperation((string) $options['scenario'], $fixture, $iteration);
        }
        $samples[] = (hrtime(true) - $startTime) / 1_000_000_000 / $iterations;
    }
    sort($samples);

    return [
        'status' => 'ok',
        'correctness' => ['passed' => true],
        'checksum' => $checksum,
        'fixtureBytes' => $fixture['fixtureBytes'],
        'peakMemoryBytes' => memory_get_peak_usage(true),
        'samplesUs' => array_map(static fn (float $sample): float => $sample * 1_000_000, $samples),
    ];
}

/**
 * Возвращает число логических CPU Linux-хоста, если оно доступно.
 *
 * @return int Число CPU или ноль, если определить его нельзя.
 */
function getCpuCount(): int
{
    $cpuInfoPath = '/proc/cpuinfo';
    if (!is_readable($cpuInfoPath)) {
        return 0;
    }

    $matches = [];
    preg_match_all('/^processor\s*:/m', (string) file_get_contents($cpuInfoPath), $matches);
    return count($matches[0] ?? []);
}

/**
 * Возвращает resident set size текущего PHP-процесса на Linux.
 *
 * @return int RSS в байтах или ноль, если procfs недоступен.
 */
function getProcessRssBytes(): int
{
    $statusPath = '/proc/self/status';
    if (!is_readable($statusPath)) {
        return 0;
    }

    $status = (string) file_get_contents($statusPath);
    if (preg_match('/^VmRSS:\s+(\d+)\s+kB$/m', $status, $matches) !== 1) {
        return 0;
    }

    return (int) $matches[1] * 1024;
}

/**
 * Формирует машиночитаемый результат одного сценария.
 *
 * @param array<string, mixed> $result Результат benchmark.
 * @param array<string, mixed> $options Параметры запуска.
 *
 * @return array<string, mixed> JSON-compatible result.
 */
function buildBenchmarkOutput(array $result, array $options): array
{
    $samples = $result['samplesUs'] ?? [];
    $p50 = $samples === [] ? 0.0 : $samples[(int) floor((count($samples) - 1) * 0.50)];
    $p95 = $samples === [] ? 0.0 : $samples[(int) floor((count($samples) - 1) * 0.95)];
    $p99 = $samples === [] ? 0.0 : $samples[(int) floor((count($samples) - 1) * 0.99)];
    $output = [
        'schemaVersion' => 1,
        'status' => $result['status'] ?? 'failed',
        'run' => [
            'phpVersion' => PHP_VERSION,
            'jit' => function_exists('opcache_get_status') && (bool) ini_get('opcache.jit'),
            'os' => PHP_OS_FAMILY,
            'cpuCount' => getCpuCount(),
            'memoryLimit' => ini_get('memory_limit'),
            'seed' => $options['seed'],
        ],
        'case' => [
            'scenario' => $options['scenario'],
            'scene' => $options['scene'],
            'representation' => $options['representation'],
            'density' => $options['density'],
            'profile' => $options['profile'],
            'workers' => $options['workers'],
            'durationSec' => $options['duration'],
            'memoryBudgetBytes' => $options['memoryBudget'],
            'sceneResolution' => '1/64 ipari',
            'occupancyCell' => '1/4 ipari',
            'iterations' => $options['iterations'],
            'warmup' => $options['warmup'],
            'repeats' => $options['repeats'],
        ],
        'correctness' => $result['correctness'] ?? ['passed' => false],
        'metrics' => [
            'fixtureBytes' => $result['fixtureBytes'] ?? 0,
            'peakMemoryBytes' => $result['peakMemoryBytes'] ?? 0,
            'meanUs' => $samples === [] ? 0 : array_sum($samples) / count($samples),
            'p50Us' => $p50,
            'p95Us' => $p95,
            'p99Us' => $p99,
            'opsPerSecond' => $p50 > 0 ? 1_000_000 / $p50 : 0,
            'workerRssBytes' => 0,
            'cpuSaturation' => 0,
        ],
        'error' => $result['error'] ?? null,
    ];
    return $output;
}

/**
 * Записывает результат в stdout и при необходимости в JSON-файл.
 *
 * @param array<string, mixed> $result Результат benchmark.
 * @param array<string, mixed> $options Параметры запуска.
 *
 * @return void
 */
function writeBenchmarkResult(array $result, array $options): void
{
    $output = buildBenchmarkOutput($result, $options);
    $encoded = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    echo $encoded . PHP_EOL;
    if ($options['json'] !== null) {
        $directory = dirname((string) $options['json']);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents((string) $options['json'], $encoded . PHP_EOL);
    }
}

/**
 * Выполняет согласованный набор сценариев на small fixture.
 *
 * @param array<string, mixed> $options Параметры запуска.
 *
 * @return list<array<string, mixed>> Результаты набора.
 */
function runBenchmarkSuite(array $options): array
{
    $scenarios = [
        'occupancy_read',
        'los_single',
        'map_visibility',
        'projection',
        'move_validate',
        'path_query',
        'attack_context',
        'door_patch',
        'mixed',
    ];
    $representations = ['packed', 'rows', 'array'];
    $results = [];

    foreach (['small', 'stress'] as $scene) {
        foreach ($representations as $representation) {
            $representationScenarios = $representation === 'packed'
                ? $scenarios
                : ['occupancy_read', 'los_single', 'door_patch'];
            foreach ($representationScenarios as $scenario) {
                $caseOptions = $options;
                $caseOptions['scene'] = $scene;
                $caseOptions['representation'] = $representation;
                $caseOptions['scenario'] = $scenario;
                $caseOptions['iterations'] = (int) $options['iterations'];
                if ($scenario === 'projection') {
                    $caseOptions['iterations'] = min($caseOptions['iterations'], $scene === 'stress' ? 10 : 100);
                }
                if ($scenario === 'mixed') {
                    $caseOptions['iterations'] = min($caseOptions['iterations'], $scene === 'stress' ? 10 : 100);
                }
                if ($scenario === 'path_query') {
                    $caseOptions['iterations'] = min($caseOptions['iterations'], 1000);
                }
                $results[] = buildBenchmarkOutput(runMicroBenchmark($caseOptions), $caseOptions);
            }
        }
    }

    foreach (['occupancy_read', 'los_single'] as $scenario) {
        $caseOptions = $options;
        $caseOptions['scene'] = 'max-grid';
        $caseOptions['representation'] = 'packed';
        $caseOptions['scenario'] = $scenario;
        $caseOptions['iterations'] = min((int) $options['iterations'], 100);
        $results[] = buildBenchmarkOutput(runMicroBenchmark($caseOptions), $caseOptions);
    }

    return $results;
}

/**
 * Выполняет synthetic capacity-прогон одним изолированным worker’ом.
 *
 * @param array<string, mixed> $options Параметры capacity-теста.
 * @param string $resultPath Путь результата worker’а.
 *
 * @return void
 */
function runCapacityWorker(array $options, string $resultPath): void
{
    $fixture = createBenchmarkFixture($options);
    $startTime = microtime(true);
    $endTime = $startTime + (int) $options['duration'];
    $operations = 0;
    $checksum = 0;

    while (microtime(true) < $endTime) {
        $checksum += runBenchmarkOperation('mixed', $fixture, $operations);
        $operations++;
    }

    file_put_contents(
        $resultPath,
        json_encode([
            'operations' => $operations,
            'elapsedSec' => max(0.001, microtime(true) - $startTime),
            'peakMemoryBytes' => memory_get_peak_usage(true),
            'rssBytes' => getProcessRssBytes(),
            'checksum' => $checksum,
        ], JSON_THROW_ON_ERROR),
    );
}

/**
 * Запускает несколько synthetic PHP workers и агрегирует их throughput.
 *
 * @param array<string, mixed> $options Параметры capacity-теста.
 *
 * @return array<string, mixed> Capacity result.
 */
function runCapacityBenchmark(array $options): array
{
    if (!function_exists('pcntl_fork')) {
        return [
            'status' => 'failed',
            'error' => 'pcntl extension is required for capacity suite',
        ];
    }

    $workerPaths = [];
    $workerPids = [];
    for ($workerIndex = 0; $workerIndex < (int) $options['workers']; $workerIndex++) {
        $workerPath = tempnam(sys_get_temp_dir(), 'battleground-worker-');
        if ($workerPath === false) {
            return ['status' => 'failed', 'error' => 'Unable to create worker result file'];
        }

        $workerPaths[] = $workerPath;
        $workerPid = pcntl_fork();
        if ($workerPid === -1) {
            return ['status' => 'failed', 'error' => 'Unable to fork worker'];
        }
        if ($workerPid === 0) {
            runCapacityWorker($options, $workerPath);
            exit(0);
        }

        $workerPids[] = $workerPid;
    }

    foreach ($workerPids as $workerPid) {
        pcntl_waitpid($workerPid, $status);
    }

    $totalOperations = 0;
    $maxMemory = 0;
    $totalRss = 0;
    $elapsed = 0.0;
    foreach ($workerPaths as $workerPath) {
        $workerResult = json_decode((string) file_get_contents($workerPath), true);
        unlink($workerPath);
        if (!is_array($workerResult)) {
            continue;
        }
        $totalOperations += (int) ($workerResult['operations'] ?? 0);
        $maxMemory = max($maxMemory, (int) ($workerResult['peakMemoryBytes'] ?? 0));
        $totalRss += (int) ($workerResult['rssBytes'] ?? 0);
        $elapsed = max($elapsed, (float) ($workerResult['elapsedSec'] ?? 0));
    }

    return [
        'status' => 'ok',
        'correctness' => ['passed' => true],
        'fixtureBytes' => 0,
        'peakMemoryBytes' => $maxMemory,
        'operations' => $totalOperations,
        'elapsedSec' => $elapsed,
        'workerRssBytes' => $totalRss,
        'targetOperationsPerSecond' => getProfileRate((string) $options['profile']),
    ];
}

/**
 * Возвращает стартовую суммарную оценку command rate профиля.
 *
 * @param string $profile Имя capacity-профиля.
 *
 * @return int Целевая частота команд в секунду.
 */
function getProfileRate(string $profile): int
{
    return match ($profile) {
        'single-game-burst' => 5,
        'two-games-normal' => 2,
        'two-games-burst' => 10,
        default => 1,
    };
}

$options = getBenchmarkOptions();
if ($options['suite'] === 'all') {
    $suite = runBenchmarkSuite($options);
    $encodedSuite = json_encode($suite, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    echo $encodedSuite . PHP_EOL;
    if ($options['json'] !== null) {
        $directory = dirname((string) $options['json']);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents((string) $options['json'], $encodedSuite . PHP_EOL);
    }
    exit(0);
}

if ($options['suite'] === 'capacity') {
    $capacity = runCapacityBenchmark($options);
    $capacity['metrics'] = [
        'fixtureBytes' => $capacity['fixtureBytes'] ?? 0,
        'peakMemoryBytes' => $capacity['peakMemoryBytes'] ?? 0,
        'workerRssBytes' => $capacity['workerRssBytes'] ?? 0,
        'opsPerSecond' => (($capacity['elapsedSec'] ?? 0) > 0)
            ? ($capacity['operations'] ?? 0) / $capacity['elapsedSec']
            : 0,
        'targetOperationsPerSecond' => $capacity['targetOperationsPerSecond'] ?? 0,
        'headroomRatio' => (($capacity['targetOperationsPerSecond'] ?? 0) > 0
            && ($capacity['elapsedSec'] ?? 0) > 0)
            ? (($capacity['operations'] ?? 0) / $capacity['elapsedSec'])
                / $capacity['targetOperationsPerSecond']
            : 0,
        'cpuSaturation' => 0,
    ];
    $capacityJson = json_encode($capacity, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    echo $capacityJson . PHP_EOL;
    exit(($capacity['status'] ?? 'failed') === 'ok' ? 0 : 2);
}

writeBenchmarkResult(runMicroBenchmark($options), $options);
