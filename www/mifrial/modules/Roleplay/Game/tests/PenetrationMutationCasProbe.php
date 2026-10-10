<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Closure;
use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\MySqlConnection;
use JsonException;
use Mifrial\Core\Kernel\Interface\Service\IApplication;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use PDO;
use PDOException;
use ReflectionProperty;
use RuntimeException;

/**
 * Поднимает committed stale version на втором соединении прямо перед CAS.
 */
final class PenetrationMutationCasProbe
{
    private ?MySqlConnection $connection = null;

    private ?PDO $writer = null;

    /** @var list<Closure>|null */
    private ?array $previousCallbacks = null;

    /** @var list<int> */
    private array $staleCharacterIds = [];

    private ?int $observeCharacterId = null;

    private bool $busy = false;

    /** @var list<array{table: string, id: int, expectedVersion: int, sheet: array<string, mixed>|null}> */
    private array $updates = [];

    /** @var array{actualVersion: int, sheet: array<string, mixed>}|null */
    private ?array $observedMutation = null;

    /** @var list<string> */
    private array $seenSql = [];

    /**
     * Открывает production MySQL-соединение тестового приложения.
     *
     * @param IApplication $application Приложение теста.
     *
     * @return MySqlConnection Соединение шлюза.
     *
     * @throws RuntimeException Если адаптер соединения чужой.
     */
    public static function connect(IApplication $application): MySqlConnection
    {
        $database = $application->getLocator()->get(ISmartTableContainer::class)->get(IDatabaseConnection::class);
        if (!$database instanceof IlluminateDatabaseConnection) {
            throw new RuntimeException('MySQL connection is unavailable');
        }

        return $database->illuminateConnection();
    }

    /**
     * Вешает hook на conditional update персонажа.
     *
     * @param MySqlConnection $connection Соединение удара.
     * @param list<int> $staleCharacterIds Персонажи, чью версию нужно состарить.
     * @param int|null $observeCharacterId Персонаж, чью мутацию нужно прочитать до stale CAS.
     *
     * @return void
     *
     * @throws PDOException Если второе соединение не открылось.
     * @throws RuntimeException Если список callback нельзя прочитать.
     */
    public function arm(MySqlConnection $connection, array $staleCharacterIds, ?int $observeCharacterId = null): void
    {
        $this->disarm();
        $this->connection = $connection;
        $this->staleCharacterIds = array_values($staleCharacterIds);
        $this->observeCharacterId = $observeCharacterId;
        $this->updates = [];
        $this->observedMutation = null;
        $this->seenSql = [];
        $this->writer = (new MySqlConnector())->connect($connection->getConfig());
        $this->writer->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $property = new ReflectionProperty($connection, 'beforeExecutingCallbacks');
        $previous = $property->getValue($connection);
        if (!is_array($previous)) {
            throw new RuntimeException('Query callbacks are unavailable');
        }

        $this->previousCallbacks = $previous;
        $property->setValue($connection, [...$previous, $this->beforeQuery()]);
    }

    /**
     * Снимает hook и второе соединение.
     *
     * @return void
     */
    public function disarm(): void
    {
        if ($this->connection instanceof MySqlConnection && is_array($this->previousCallbacks)) {
            $property = new ReflectionProperty($this->connection, 'beforeExecutingCallbacks');
            $property->setValue($this->connection, $this->previousCallbacks);
        }

        $this->previousCallbacks = null;
        $this->writer = null;
    }

    /**
     * Возвращает увиденные conditional update.
     *
     * @return list<array{table: string, id: int, expectedVersion: int, sheet: array<string, mixed>|null}> Записи.
     */
    public function getUpdates(): array
    {
        return $this->updates;
    }

    /**
     * Возвращает мутацию, видимую внутри открытой транзакции удара.
     *
     * @return array{actualVersion: int, sheet: array<string, mixed>}|null Строка или null.
     */
    public function getObservedMutation(): ?array
    {
        return $this->observedMutation;
    }

    /**
     * Возвращает SQL, который hook увидел и не принял за CAS.
     *
     * @return list<string> Запросы.
     */
    public function getSeenSql(): array
    {
        return $this->seenSql;
    }

    /**
     * Создаёт callback перед исполнением запроса.
     *
     * @return Closure(string, array<int, mixed>, MySqlConnection): void Callback.
     */
    private function beforeQuery(): Closure
    {
        return function (string $query, array $bindings, MySqlConnection $connection): void {
            $this->inspect($query, $bindings, $connection);
        };
    }

    /**
     * Состаривает версию непосредственно перед CAS и запоминает payload.
     *
     * @param string $query SQL.
     * @param array<int, mixed> $bindings Параметры.
     * @param MySqlConnection $connection Соединение удара.
     *
     * @return void
     *
     * @throws PDOException Если stale update не записался.
     * @throws RuntimeException Если предыдущая мутация не читается.
     */
    private function inspect(string $query, array $bindings, MySqlConnection $connection): void
    {
        if ($this->busy || $connection->transactionLevel() < 1) {
            return;
        }

        $table = $this->tableOf($query);
        $assigned = $table === null ? null : $this->assigned($query, $bindings);
        $rowId = $this->integerBinding($assigned['where']['id'] ?? null);
        $expectedVersion = $this->integerBinding($assigned['where']['actual_version'] ?? null);
        if ($table === null || $assigned === null || $rowId === null || $expectedVersion === null) {
            $this->rememberSql($query);

            return;
        }

        if ($table === 'character' && in_array($rowId, $this->staleCharacterIds, true)) {
            $this->capturePriorMutation($connection);
            $this->bump($rowId);
        }

        $payload = $assigned['set']['sheet'] ?? null;
        $this->updates[] = [
            'table' => $table,
            'id' => $rowId,
            'expectedVersion' => $expectedVersion,
            'sheet' => $this->sheetOf($payload),
        ];
    }

    /**
     * Читает уже выполненную мутацию первой цели в той же транзакции.
     *
     * @param MySqlConnection $connection Соединение удара.
     *
     * @return void
     *
     * @throws RuntimeException Если строка не видна.
     */
    private function capturePriorMutation(MySqlConnection $connection): void
    {
        if ($this->observeCharacterId === null || $this->observedMutation !== null) {
            return;
        }

        $this->observedMutation = $this->readCharacter($connection, $this->observeCharacterId);
    }

    /**
     * Читает лист персонажа, не запуская повторный hook.
     *
     * @param MySqlConnection $connection Соединение удара.
     * @param int $characterId Персонаж.
     *
     * @return array{actualVersion: int, sheet: array<string, mixed>} Строка.
     *
     * @throws RuntimeException Если строка не видна.
     */
    private function readCharacter(MySqlConnection $connection, int $characterId): array
    {
        $this->busy = true;
        try {
            $rows = $connection->select(
                'select actual_version, sheet from `character` where id = ?',
                [$characterId],
            );
        } finally {
            $this->busy = false;
        }

        $row = $rows[0] ?? null;
        $version = is_object($row) ? $this->integerBinding($row->actual_version ?? null) : null;
        $sheet = is_object($row) ? $this->sheetOf($row->sheet ?? null) : null;
        if ($version === null || $sheet === null) {
            throw new RuntimeException('Character mutation row is invalid');
        }

        return ['actualVersion' => $version, 'sheet' => $sheet];
    }

    /**
     * Коммитит увеличение actual_version отдельным соединением.
     *
     * @param int $characterId Персонаж.
     *
     * @return void
     *
     * @throws PDOException Если запись не прошла.
     * @throws RuntimeException Если второе соединение снято.
     */
    private function bump(int $characterId): void
    {
        if (!$this->writer instanceof PDO) {
            throw new RuntimeException('Stale writer is unavailable');
        }

        $statement = $this->writer->prepare(
            'UPDATE `character` SET actual_version = actual_version + 1 WHERE id = ?',
        );
        $statement->execute([$characterId]);
    }

    /**
     * Возвращает таблицу conditional update.
     *
     * @param string $query SQL.
     *
     * @return string|null character или null.
     */
    private function tableOf(string $query): ?string
    {
        if (preg_match('/update\s+`character`/i', $query) !== 1) {
            return null;
        }

        return 'character';
    }

    /**
     * Разбирает SET и WHERE conditional update.
     *
     * @param string $query SQL.
     * @param array<int, mixed> $bindings Параметры.
     *
     * @return array{set: array<string, mixed>, where: array<string, mixed>}|null Колонки.
     */
    private function assigned(string $query, array $bindings): ?array
    {
        $whereAt = stripos($query, ' where ');
        if ($whereAt === false) {
            return null;
        }

        $setSql = substr($query, 0, $whereAt);
        $whereSql = substr($query, $whereAt);
        $setCount = substr_count($setSql, '?');

        return [
            'set' => $this->pairs($setSql, array_slice($bindings, 0, $setCount)),
            'where' => $this->pairs($whereSql, array_slice($bindings, $setCount)),
        ];
    }

    /**
     * Сопоставляет колонки `name` = ? с параметрами.
     *
     * @param string $sql Фрагмент SQL.
     * @param array<int, mixed> $bindings Параметры фрагмента.
     *
     * @return array<string, mixed> Колонка → значение.
     */
    private function pairs(string $sql, array $bindings): array
    {
        preg_match_all('/`([A-Za-z0-9_]+)`\s*=\s*\?/', $sql, $matches);
        $pairs = [];
        foreach ($matches[1] as $index => $column) {
            if (!is_string($column)) {
                continue;
            }

            $pairs[$column] = $bindings[$index] ?? null;
        }

        return $pairs;
    }

    /**
     * Приводит binding к целому.
     *
     * @param mixed $value Значение.
     *
     * @return int|null Целое или null.
     */
    private function integerBinding(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Декодирует JSON листа из payload.
     *
     * @param mixed $value Значение колонки.
     *
     * @return array<string, mixed>|null Лист или null.
     */
    private function sheetOf(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Запоминает короткий хвост чужого SQL для диагностики.
     *
     * @param string $query SQL.
     *
     * @return void
     */
    private function rememberSql(string $query): void
    {
        if (count($this->seenSql) < 20 && preg_match('/^\s*update\s/i', $query) === 1) {
            $this->seenSql[] = $query;
        }
    }
}
