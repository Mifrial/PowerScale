<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use JsonException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Общая граница replay и атомарной записи команды.
 */
final class GameReplayTransaction
{
    /**
     * Создаёт границу replay.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз транзакций.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
    ) {
    }

    /**
     * Читает и сверяет сохранённую команду.
     *
     * @param Closure $lookup Поиск по игре и ключу.
     * @param array<string, mixed> $requestBody Тело запроса.
     *
     * @return array<string, mixed>|null Сохранённый итог или null.
     *
     * @throws GameBattleConflictException Если тело отличается.
     * @throws GameInvalidException Если JSON тела битый.
     */
    public function find(Closure $lookup, array $requestBody): ?array
    {
        $stored = $lookup();
        if ($stored === null) {
            return null;
        }

        try {
            $same = json_encode($this->sortKeys($stored['body']), JSON_THROW_ON_ERROR)
                === json_encode($this->sortKeys($requestBody), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GameInvalidException('Game command body is invalid', $exception);
        }

        if (!$same) {
            throw new GameBattleConflictException(null);
        }

        return $stored['result'];
    }

    /**
     * Резервирует ключ, выполняет работу и сохраняет итог.
     *
     * @param Closure $lookup Поиск команды после конфликта.
     * @param Closure $reserve Резервирование ключа и тела.
     * @param Closure $complete Запись результата по reservation id.
     * @param Closure $work Изменения домена.
     * @param array<string, mixed> $requestBody Тело запроса.
     *
     * @return array{result: array<string, mixed>, replay: bool} Итог и replay.
     *
     * @throws GameBattleConflictException Если ключ занят другим телом.
     * @throws GameInvalidException Если reservation или итог некорректны.
     */
    public function execute(
        Closure $lookup,
        Closure $reserve,
        Closure $complete,
        Closure $work,
        array $requestBody,
    ): array {
        $replay = $this->find($lookup, $requestBody);
        if ($replay !== null) {
            return ['result' => $replay, 'replay' => true];
        }

        try {
            return [
                'result' => $this->smartTableGateway->transaction(
                    fn (): array => $this->commit($reserve, $complete, $work),
                ),
                'replay' => false,
            ];
        } catch (GameBattleConflictException $exception) {
            return $this->recoverConflict($lookup, $requestBody, $exception);
        }
    }

    /**
     * Выполняет резервирование, доменную работу и завершение команды.
     *
     * @param Closure $reserve Резервирование команды.
     * @param Closure $complete Завершение команды.
     * @param Closure $work Изменения домена.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если итог некорректен.
     */
    private function commit(Closure $reserve, Closure $complete, Closure $work): array
    {
        $reservationId = $reserve();
        $result = $work();
        if (!is_array($result)) {
            throw new GameInvalidException('Game command transaction result is invalid');
        }

        $complete($reservationId, $result);

        return $result;
    }

    /**
     * Восстанавливает результат после конфликта reservation.
     *
     * @param Closure $lookup Поиск команды.
     * @param array<string, mixed> $requestBody Тело команды.
     * @param GameBattleConflictException $exception Исходный конфликт.
     *
     * @return array{result: array<string, mixed>, replay: bool} Итог и replay.
     *
     * @throws GameBattleConflictException Если команда не найдена или тело отличается.
     */
    private function recoverConflict(
        Closure $lookup,
        array $requestBody,
        GameBattleConflictException $exception,
    ): array {
        $replay = $this->find($lookup, $requestBody);
        if ($replay === null) {
            throw $exception;
        }

        return ['result' => $replay, 'replay' => true];
    }

    /**
     * Стабильно сортирует ключи документа.
     *
     * @param mixed $value Документ.
     *
     * @return mixed Документ с отсортированными ключами.
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
