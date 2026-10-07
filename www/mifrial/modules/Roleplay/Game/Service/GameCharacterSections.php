<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Character\Enum\CharacterSheetSection;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;

/**
 * Запись секций видимости строки персонажа. Лист и snapshot не меняет.
 */
final class GameCharacterSections
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGames $games Игра и роль.
     * @param GameCharacterRepository $characterRepository Строка.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGames $games,
        private readonly GameCharacterRepository $characterRepository,
    ) {
    }

    /**
     * Пишет список кодов на живую строку.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param array<mixed> $sectionVisibility Коды из JSON.
     *
     * @return void
     *
     * @throws ActionException AUTH или INVALID_PARAMS.
     * @throws GameNotFoundException Если нет права, игры или строки.
     * @throws GameInvalidException Если completed или left.
     */
    public function set(int $gameId, int $characterId, array $sectionVisibility): void
    {
        $this->assertModerator($gameId);
        $this->assertWritableGame($gameId);
        $row = $this->characterRepository->getByPair($gameId, $characterId);
        if ($row->getStatus() === 'left') {
            throw new GameInvalidException('Game character has left');
        }

        $this->characterRepository->saveSectionVisibility($row->getId(), $this->normalize($sectionVisibility));
    }

    /**
     * game.moderate или game.edit_all.
     *
     * @param int $gameId Игра.
     *
     * @return void
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет права или игры.
     */
    private function assertModerator(int $gameId): void
    {
        $actor = $this->userAccess->requireActor();
        if ($actor->hasKey(GamePermissionKeys::EDIT_ALL)) {
            return;
        }

        $game = $this->games->get($gameId);
        $role = $this->roleOf($gameId, $actor->getUserId());
        $keys = GamePermissionKeys::keysFor($game->getOwnerId() === $actor->getUserId(), $role);
        if (!in_array(GamePermissionKeys::MODERATE, $keys, true)) {
            throw new GameNotFoundException();
        }
    }

    /**
     * Роль участника или null.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     *
     * @return string|null Роль.
     */
    private function roleOf(int $gameId, int $userId): ?string
    {
        try {
            return $this->games->getMember($gameId, $userId)->getRole();
        } catch (GameNotFoundException) {
            return null;
        }
    }

    /**
     * Игра есть и не completed.
     *
     * @param int $gameId Игра.
     *
     * @return void
     *
     * @throws GameNotFoundException Если игры нет.
     * @throws GameInvalidException Если completed.
     */
    private function assertWritableGame(int $gameId): void
    {
        if ($this->games->get($gameId)->isCompleted()) {
            throw new GameInvalidException('Completed game is read-only');
        }
    }

    /**
     * Уникальный отсортированный список восьми кодов.
     *
     * @param array<mixed> $sectionVisibility Вход.
     *
     * @return list<string> Коды.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function normalize(array $sectionVisibility): array
    {
        if (!array_is_list($sectionVisibility)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: sectionVisibility');
        }

        $known = [];
        foreach ($sectionVisibility as $code) {
            $accepted = $this->requireCode($code, $known);
            $known[$accepted] = $accepted;
        }

        $normalized = array_values($known);
        sort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * Код секции ещё не встречался.
     *
     * @param mixed $code Элемент.
     * @param array<string, string> $known Уже принятые.
     *
     * @return string Код.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function requireCode(mixed $code, array $known): string
    {
        if (!is_string($code) || CharacterSheetSection::tryFrom($code) === null) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: sectionVisibility');
        }

        if (isset($known[$code])) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: sectionVisibility');
        }

        return $code;
    }
}
