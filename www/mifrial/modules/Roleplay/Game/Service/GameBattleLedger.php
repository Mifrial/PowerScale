<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use JsonException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;

/**
 * Журнал боевых команд: повтор ключа и одна транзакция.
 */
final class GameBattleLedger
{
    /**
     * Создаёт журнал.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param GameCommandRepository $commands Команды.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
        private readonly GameCommandRepository $commands,
    ) {
    }

    /**
     * Сохранённая команда или null.
     *
     * @param int $gameId Игра.
     * @param string $kind Вид команды.
     * @param string $idempotencyKey Ключ.
     *
     * @return array<string, mixed>|null Команда.
     */
    public function find(int $gameId, string $kind, string $idempotencyKey): ?array
    {
        return $this->commands->findByKey($gameId, $kind, $idempotencyKey);
    }

    /**
     * Повтор ключа или null.
     *
     * @param int $gameId Игра.
     * @param string $kind Вид команды.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     * @param Closure $present Представление сохранённого итога.
     *
     * @return array<string, mixed>|null Итог.
     *
     * @throws GameBattleConflictException Если тело другое.
     * @throws GameInvalidException Если JSON.
     */
    public function replay(
        int $gameId,
        string $kind,
        string $idempotencyKey,
        array $body,
        Closure $present,
    ): ?array {
        $stored = $this->find($gameId, $kind, $idempotencyKey);
        if ($stored === null) {
            return null;
        }

        if ($this->canonical($stored['body']) !== $this->canonical($body)) {
            throw new GameBattleConflictException(null);
        }

        return $present($stored['result']);
    }

    /**
     * Одна транзакция записи.
     *
     * @param Closure $work Работа внутри транзакции.
     *
     * @return array<string, mixed> Итог.
     */
    public function transaction(Closure $work): array
    {
        return $this->smartTableGateway->transaction($work);
    }

    /**
     * Записывает выполненную команду.
     *
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param string $kind Вид команды.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     */
    public function record(
        int $gameId,
        int $sessionId,
        string $kind,
        string $idempotencyKey,
        array $body,
        array $result,
    ): void {
        $this->commands->add($gameId, $sessionId, $kind, $idempotencyKey, $body, $result);
    }

    /**
     * Стабильный JSON.
     *
     * @param mixed $value Значение.
     *
     * @return string JSON.
     *
     * @throws GameInvalidException Если значение не кодируется.
     */
    private function canonical(mixed $value): string
    {
        try {
            $encoded = json_encode($this->sortKeys($value), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GameInvalidException('Game battle body is invalid', $exception);
        }

        return $encoded;
    }

    /**
     * Сортирует ключи объектов.
     *
     * @param mixed $value Значение.
     *
     * @return mixed Значение.
     */
    private function sortKeys(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->sortKeys(...), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortKeys($item);
        }

        return $value;
    }
}
