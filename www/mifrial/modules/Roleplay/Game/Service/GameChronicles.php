<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Game\Dto\GameChronicleEntryRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameChronicles;
use Mifrial\Roleplay\Game\Repository\GameChronicleEntryRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameRepository;

/**
 * Летопись одной игры. Лист, сессию и бой не пишет.
 */
final class GameChronicles implements IGameChronicles
{
    /**
     * Создаёт сценарий.
     *
     * @param GameRepository $gameRepository Строка игры.
     * @param GameMemberRepository $memberRepository Участники.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     * @param GameChronicleEntryRepository $entryRepository Записи.
     * @param GameTimeOffset $timeOffset Единицы времени.
     *
     * @return void
     */
    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly GameMemberRepository $memberRepository,
        private readonly GameCardAccess $cardAccess,
        private readonly GameChronicleEntryRepository $entryRepository,
        private readonly GameTimeOffset $timeOffset,
    ) {
    }

    /**
     * Открывает карточку для чтения летописи.
     *
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $gameId Игра.
     *
     * @return void
     *
     * @throws GameNotFoundException Если игры нет или карточка скрыта.
     */
    public function requireVisible(int $actorUserId, bool $viewAll, int $gameId): void
    {
        $this->visibleGame($actorUserId, $viewAll, $gameId);
    }

    /**
     * Список по смещению.
     *
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $gameId Игра.
     *
     * @return list<GameChronicleEntryRecord> Строки.
     *
     * @throws GameNotFoundException Если карточка скрыта.
     * @throws GameInvalidException Если строка битая.
     */
    public function getEntries(int $actorUserId, bool $viewAll, int $gameId): array
    {
        $this->visibleGame($actorUserId, $viewAll, $gameId);

        return $this->entryRepository->getListByGame($gameId);
    }

    /**
     * Создаёт запись.
     *
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param bool $editAll Ключ game.edit_all.
     * @param int $gameId Игра.
     * @param string $title Заголовок.
     * @param string $content Текст.
     * @param array<mixed> $offset Шесть единиц.
     *
     * @return GameChronicleEntryRecord Строка.
     *
     * @throws GameNotFoundException Если карточка скрыта.
     * @throws GameInvalidException Если время или заголовок.
     * @throws ActionException AUTH_DENIED если писать нельзя.
     */
    public function addEntry(
        int $actorUserId,
        bool $viewAll,
        bool $editAll,
        int $gameId,
        string $title,
        string $content,
        array $offset,
    ): GameChronicleEntryRecord {
        $game = $this->visibleGame($actorUserId, $viewAll, $gameId);
        $this->assertWriter($game, $actorUserId, $editAll);
        $entryId = $this->entryRepository->add(
            $gameId,
            $this->title($title),
            $content,
            $this->timeOffset->toMinutes($offset),
            $actorUserId,
            DateTime::now(),
        );

        return $this->entryRepository->getById($entryId);
    }

    /**
     * Меняет запись.
     *
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param bool $editAll Ключ game.edit_all.
     * @param int $entryId Запись.
     * @param string $title Заголовок.
     * @param string $content Текст.
     * @param array<mixed> $offset Шесть единиц.
     *
     * @return GameChronicleEntryRecord Строка.
     *
     * @throws GameNotFoundException Если записи нет или карточка скрыта.
     * @throws GameInvalidException Если время или заголовок.
     * @throws ActionException AUTH_DENIED если писать нельзя.
     */
    public function updateEntry(
        int $actorUserId,
        bool $viewAll,
        bool $editAll,
        int $entryId,
        string $title,
        string $content,
        array $offset,
    ): GameChronicleEntryRecord {
        $entry = $this->entryRepository->getById($entryId);
        $game = $this->visibleGame($actorUserId, $viewAll, $entry->getGameId());
        $this->assertWriter($game, $actorUserId, $editAll);
        $this->entryRepository->update(
            $entryId,
            $this->title($title),
            $content,
            $this->timeOffset->toMinutes($offset),
            DateTime::now(),
        );

        return $this->entryRepository->getById($entryId);
    }

    /**
     * Удаляет запись.
     *
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param bool $editAll Ключ game.edit_all.
     * @param int $entryId Запись.
     *
     * @return void
     *
     * @throws GameNotFoundException Если записи нет или карточка скрыта.
     * @throws ActionException AUTH_DENIED если писать нельзя.
     */
    public function deleteEntry(int $actorUserId, bool $viewAll, bool $editAll, int $entryId): void
    {
        $entry = $this->entryRepository->getById($entryId);
        $game = $this->visibleGame($actorUserId, $viewAll, $entry->getGameId());
        $this->assertWriter($game, $actorUserId, $editAll);
        $this->entryRepository->deleteById($entryId);
    }

    /**
     * Карточка или скрытая игра.
     *
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $gameId Игра.
     *
     * @return GameRecord Строка.
     *
     * @throws GameNotFoundException Если нет или скрыта.
     */
    private function visibleGame(int $actorUserId, bool $viewAll, int $gameId): GameRecord
    {
        $game = $this->gameRepository->getById($gameId);
        if (!$this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            throw new GameNotFoundException();
        }

        return $game;
    }

    /**
     * Владелец, gm или game.edit_all.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED если писать нельзя.
     */
    private function assertWriter(GameRecord $game, int $actorUserId, bool $editAll): void
    {
        if ($game->getOwnerId() === $actorUserId || $editAll || $this->isGm($game->getId(), $actorUserId)) {
            return;
        }

        throw new ActionException('AUTH_DENIED', 'Permission denied');
    }

    /**
     * Роль gm на этой игре.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     *
     * @return bool Да, если строка gm.
     */
    private function isGm(int $gameId, int $actorUserId): bool
    {
        try {
            $member = $this->memberRepository->getByPair($gameId, $actorUserId);
        } catch (GameNotFoundException) {
            return false;
        }

        return $member->getRole() === 'gm';
    }

    /**
     * Заголовок без краевых пробелов.
     *
     * @param string $title Текст.
     *
     * @return string Заголовок.
     *
     * @throws GameInvalidException Если пусто.
     */
    private function title(string $title): string
    {
        $trimmed = trim($title);
        if ($trimmed === '') {
            throw new GameInvalidException('Game chronicle title is empty');
        }

        return $trimmed;
    }
}
