<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\CreateGameNpcInput;
use Mifrial\Roleplay\Game\Dto\Action\GetGameNpcInput;
use Mifrial\Roleplay\Game\Dto\Action\TranslateGameNpcInput;
use Mifrial\Roleplay\Game\Dto\Action\UpdateGameNpcInput;
use Mifrial\Roleplay\Game\Dto\GameNpcRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;

/**
 * HTTP NPC. Сессию не открывает.
 */
final class GameNpcHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGames $games Игра и роль.
     * @param GameNpcs $npcs Строка NPC.
     * @param GameNpcVisibility $npcVisibility Объект видимости.
     * @param GameNpcView $npcView JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGames $games,
        private readonly GameNpcs $npcs,
        private readonly GameNpcVisibility $npcVisibility,
        private readonly GameNpcView $npcView,
    ) {
    }

    /**
     * Создаёт NPC.
     *
     * @param CreateGameNpcInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws ActionException AUTH или INVALID_PARAMS.
     * @throws GameNotFoundException Если нет права или игры.
     * @throws GameInvalidException Если completed.
     */
    public function create(CreateGameNpcInput $input): array
    {
        $this->assertEditor($input->gameId);
        $record = $this->npcs->add($input->gameId, $input->name, $this->npcVisibility->normalize($input->visibility));

        return $this->npcView->detail($record, true);
    }

    /**
     * Читает NPC и режет секции.
     *
     * @param GetGameNpcInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет права, строки или scope.
     */
    public function get(GetGameNpcInput $input): array
    {
        $actorUserId = $this->userAccess->requireActor()->getUserId();
        $game = $this->games->get($input->gameId);
        $this->assertReader($game, $actorUserId);
        $record = $this->npcs->get($input->gameId, $input->npcId);

        return $this->npcView->detail($record, $this->allows($game, $record, $actorUserId));
    }

    /**
     * Меняет лист на той же ревизии.
     *
     * @param UpdateGameNpcInput $input JSON.
     *
     * @return array<string, mixed> Строка или conflicts.
     *
     * @throws ActionException AUTH или INVALID_PARAMS.
     * @throws GameNotFoundException Если нет права.
     * @throws GameInvalidException Если мир не тот.
     */
    public function update(UpdateGameNpcInput $input): array
    {
        $this->assertEditor($input->gameId);
        $game = $this->npcs->openGame($input->gameId);
        $record = $this->npcs->get($input->gameId, $input->npcId);
        $this->assertSameWorld($record, $input->spaceId, $input->spaceCode, $input->rulesRevision);
        $changed = $this->npcs->change(
            $record,
            $game,
            $input->name,
            $input->choices,
            $input->sheet,
            $this->npcVisibility->normalize($input->visibility),
            $input->expectedNpcActualVersion,
        );

        return $this->changed($changed, true);
    }

    /**
     * Переводит лист на ревизию игры.
     *
     * @param TranslateGameNpcInput $input JSON.
     *
     * @return array<string, mixed> Строка или conflicts.
     *
     * @throws ActionException AUTH или INVALID_PARAMS.
     * @throws GameNotFoundException Если нет права.
     * @throws GameInvalidException Если мир не тот или ревизия уже совпала.
     */
    public function translate(TranslateGameNpcInput $input): array
    {
        $this->assertEditor($input->gameId);
        $game = $this->npcs->openGame($input->gameId);
        $record = $this->npcs->get($input->gameId, $input->npcId);
        $this->assertSameWorld($record, $input->spaceId, $input->spaceCode, $input->rulesRevision);
        $changed = $this->npcs->translate($record, $game, $input->expectedNpcActualVersion);

        return $this->changed($changed, true);
    }

    /**
     * Строка или conflicts.
     *
     * @param array{record: GameNpcRecord|null, report: array<string, mixed>} $changed Результат.
     * @param bool $fullSheet Ведущий пишет и видит лист.
     *
     * @return array<string, mixed> JSON.
     */
    private function changed(array $changed, bool $fullSheet): array
    {
        if (!$changed['record'] instanceof GameNpcRecord) {
            return $changed['report'];
        }

        return $this->npcView->detail($changed['record'], $fullSheet);
    }

    /**
     * true, если актор видит лист целиком.
     *
     * @param GameRecord $game Игра.
     * @param GameNpcRecord $record Строка.
     * @param int $actorUserId Актор.
     *
     * @return bool true для владельца и gm.
     *
     * @throws GameNotFoundException Если scope не пускает.
     */
    private function allows(GameRecord $game, GameNpcRecord $record, int $actorUserId): bool
    {
        if ($game->getOwnerId() === $actorUserId || $this->roleOf($game->getId(), $actorUserId) === 'gm') {
            return true;
        }

        if ($this->scopeAllows($record->getVisibility(), $game->getId(), $actorUserId)) {
            return false;
        }

        throw new GameNotFoundException();
    }

    /**
     * Scope пускает игрока к урезанному листу.
     *
     * @param array<string, mixed> $visibility Объект.
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     *
     * @return bool true, если секции можно показать.
     */
    private function scopeAllows(array $visibility, int $gameId, int $actorUserId): bool
    {
        $scope = $visibility['scope'] ?? null;
        if ($scope === 'all') {
            return $this->roleOf($gameId, $actorUserId) !== null;
        }

        if ($scope !== 'users' || !is_array($visibility['userIds'] ?? null)) {
            return false;
        }

        return in_array($actorUserId, $visibility['userIds'], true);
    }

    /**
     * Читатель карточки NPC, не game.get.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     *
     * @return void
     *
     * @throws GameNotFoundException Если чужой.
     */
    private function assertReader(GameRecord $game, int $actorUserId): void
    {
        $actor = $this->userAccess->requireActor();
        $allowed = $game->getOwnerId() === $actorUserId
            || $actor->hasKey(GamePermissionKeys::VIEW_ALL)
            || $this->roleOf($game->getId(), $actorUserId) !== null;
        if (!$allowed) {
            throw new GameNotFoundException();
        }
    }

    /**
     * game.edit этой игры или game.edit_all.
     *
     * @param int $gameId Игра.
     *
     * @return void
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если права нет.
     */
    private function assertEditor(int $gameId): void
    {
        $actor = $this->userAccess->requireActor();
        if ($actor->hasKey(GamePermissionKeys::EDIT_ALL)) {
            return;
        }

        $game = $this->games->get($gameId);
        $keys = GamePermissionKeys::keysFor(
            $game->getOwnerId() === $actor->getUserId(),
            $this->roleOf($gameId, $actor->getUserId()),
        );
        if (!in_array(GamePermissionKeys::EDIT, $keys, true)) {
            throw new GameNotFoundException();
        }
    }

    /**
     * Роль строки участника или null.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     *
     * @return string|null gm, player или null.
     */
    private function roleOf(int $gameId, int $actorUserId): ?string
    {
        try {
            return $this->games->getMember($gameId, $actorUserId)->getRole();
        } catch (GameNotFoundException) {
            return null;
        }
    }

    /**
     * Присланный мир совпадает со строкой, если ключ есть.
     *
     * @param GameNpcRecord $record Строка.
     * @param int|null $spaceId Мир.
     * @param string|null $spaceCode Код.
     * @param int|null $rulesRevision Ревизия листа.
     *
     * @return void
     *
     * @throws GameInvalidException Если значение другое.
     */
    private function assertSameWorld(
        GameNpcRecord $record,
        ?int $spaceId,
        ?string $spaceCode,
        ?int $rulesRevision,
    ): void {
        $version = $record->getVersion();
        $same = ($spaceId === null || $spaceId === ($version['spaceId'] ?? null))
            && ($spaceCode === null || $spaceCode === ($version['spaceCode'] ?? null))
            && ($rulesRevision === null || $rulesRevision === ($version['rulesRevision'] ?? null));
        if (!$same) {
            throw new GameInvalidException('NPC world does not match the sheet');
        }
    }
}
