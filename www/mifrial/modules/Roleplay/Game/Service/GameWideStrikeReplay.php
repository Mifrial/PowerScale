<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use JsonException;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameWideStrikeCommandRepository;

/**
 * Повтор команды широкого удара по телу ключа.
 */
final class GameWideStrikeReplay
{
    /**
     * Создаёт сверку.
     *
     * @param GameWideStrikeCommandRepository $commands Журнал.
     *
     * @return void
     */
    public function __construct(
        private readonly GameWideStrikeCommandRepository $commands,
    ) {
    }

    /**
     * Сохранённый итог или null.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $requestBody Тело.
     *
     * @return array<string, mixed>|null Итог или null.
     *
     * @throws GameBattleConflictException Если тело другое.
     * @throws GameInvalidException Если JSON битый.
     */
    public function find(int $gameId, string $idempotencyKey, array $requestBody): ?array
    {
        $stored = $this->commands->findByKey($gameId, $idempotencyKey);
        if ($stored === null) {
            return null;
        }

        try {
            $same = json_encode($this->sortKeys($stored['body']), JSON_THROW_ON_ERROR)
                === json_encode($this->sortKeys($requestBody), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GameInvalidException('Game wide strike body is invalid', $exception);
        }

        if (!$same) {
            throw new GameBattleConflictException(null);
        }

        return $stored['result'];
    }

    /**
     * Стабильный порядок ключей.
     *
     * @param mixed $value Документ.
     *
     * @return mixed Документ.
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
