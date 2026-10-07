<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\CreateGameChronicleEntryInput;
use Mifrial\Roleplay\Game\Dto\Action\DeleteGameChronicleEntryInput;
use Mifrial\Roleplay\Game\Dto\Action\GetGameChronicleEntriesInput;
use Mifrial\Roleplay\Game\Dto\Action\GetGameChronicleInput;
use Mifrial\Roleplay\Game\Dto\Action\UpdateGameChronicleEntryInput;
use Mifrial\Roleplay\Game\Dto\GameChronicleEntryRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameChronicles;

/**
 * HTTP летописи. Не метод GameHttp.
 */
final class GameChronicleHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGameChronicles $chronicles Записи.
     * @param GameTimeOffset $timeOffset Единицы ответа.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGameChronicles $chronicles,
        private readonly GameTimeOffset $timeOffset,
    ) {
    }

    /**
     * Шапка без строки в таблице.
     *
     * @param GetGameChronicleInput $input JSON.
     *
     * @return array<string, mixed> Шапка.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если карточка скрыта.
     */
    public function getChronicle(GetGameChronicleInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $this->chronicles->requireVisible(
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->gameId,
        );

        return [
            'id' => $input->gameId,
            'gameId' => $input->gameId,
            'name' => null,
            'epoch' => 'adventure_start',
        ];
    }

    /**
     * Список по смещению.
     *
     * @param GetGameChronicleEntriesInput $input JSON.
     *
     * @return list<array<string, mixed>> Записи.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если карточка скрыта.
     * @throws GameInvalidException Если строка битая.
     */
    public function getEntries(GetGameChronicleEntriesInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $entries = $this->chronicles->getEntries(
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->gameId,
        );
        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = $this->entryJson($entry);
        }

        return $rows;
    }

    /**
     * Создаёт запись.
     *
     * @param CreateGameChronicleEntryInput $input JSON.
     *
     * @return array<string, mixed> Запись.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     * @throws GameNotFoundException Если карточка скрыта.
     * @throws GameInvalidException Если время или заголовок.
     */
    public function create(CreateGameChronicleEntryInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->entryJson($this->chronicles->addEntry(
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $input->gameId,
            $input->title,
            $input->content,
            $input->offset,
        ));
    }

    /**
     * Меняет запись.
     *
     * @param UpdateGameChronicleEntryInput $input JSON.
     *
     * @return array<string, mixed> Запись.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     * @throws GameNotFoundException Если записи нет или карточка скрыта.
     * @throws GameInvalidException Если время или заголовок.
     */
    public function update(UpdateGameChronicleEntryInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->entryJson($this->chronicles->updateEntry(
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $input->entryId,
            $input->title,
            $input->content,
            $input->offset,
        ));
    }

    /**
     * Удаляет запись.
     *
     * @param DeleteGameChronicleEntryInput $input JSON.
     *
     * @return null Пусто.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     * @throws GameNotFoundException Если записи нет или карточка скрыта.
     */
    public function delete(DeleteGameChronicleEntryInput $input): null
    {
        $actor = $this->userAccess->requireActor();
        $this->chronicles->deleteEntry(
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $input->entryId,
        );

        return null;
    }

    /**
     * JSON записи.
     *
     * @param GameChronicleEntryRecord $entry Строка.
     *
     * @return array<string, mixed> Контракт.
     */
    private function entryJson(GameChronicleEntryRecord $entry): array
    {
        return [
            'id' => $entry->getId(),
            'gameId' => $entry->getGameId(),
            'title' => $entry->getTitle(),
            'content' => $entry->getContent(),
            'offset' => $this->timeOffset->toParts($entry->getOffsetMinutes()),
            'related' => $this->related($entry->getContent()),
            'createdBy' => $entry->getCreatedBy(),
            'createdAt' => $entry->getCreatedAt()->toUnix(),
            'updatedAt' => $entry->getUpdatedAt()->toUnix(),
        ];
    }

    /**
     * Токены [[character:N]] и [[npc:N]] без проверки существования.
     *
     * @param string $content Текст.
     *
     * @return list<array{kind: string, id: int}> Ссылки.
     */
    private function related(string $content): array
    {
        $matched = preg_match_all('/\[\[(character|npc):([1-9]\d*)\]\]/', $content, $matches, PREG_SET_ORDER);
        if ($matched === false) {
            return [];
        }

        $refs = [];
        $seen = [];
        foreach ($matches as $match) {
            $id = (int) $match[2];
            if ((string) $id !== $match[2]) {
                continue;
            }

            $key = $match[1] . ':' . $match[2];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $refs[] = ['kind' => $match[1], 'id' => $id];
        }

        return $refs;
    }
}
