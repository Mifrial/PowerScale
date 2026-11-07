<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Строка удара, участники и их снимки для расчёта.
 */
final class GameStrikeResolutionContext
{
    /**
     * Создаёт контекст строки удара.
     *
     * @param GameStrikeRules $rules Срезы и данные участников.
     * @param GameStrikeSheets $sheets Листы удара.
     * @param GameNpcRepository $npcs NPC.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly GameNpcRepository $npcs,
    ) {
    }

    /**
     * Проверенные поля атакующего из строки удара.
     *
     * @param array<string, mixed> $open Строка удара.
     *
     * @return array{
     *     kind: string,
     *     attackerId: int,
     *     itemRuleCode: string,
     *     profileType: string,
     *     profileIndex: int,
     *     inventoryId: int,
     *     actionRuleCode: string
     * }
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function attackerOf(array $open): array
    {
        $kind = $this->requireString($open['attacker_kind'] ?? null, 'Game strike row is invalid');
        $attackerId = $this->requireInt($open['attacker_id'] ?? null, 'Game strike row is invalid');
        $itemRuleCode = $this->requireString($open['item_rule_code'] ?? null, 'Game strike row is invalid');
        $profileType = $this->requireString($open['profile_type'] ?? null, 'Game strike row is invalid');
        $profileIndex = $this->requireInt($open['profile_index'] ?? null, 'Game strike row is invalid');
        $inventoryId = $this->requireInt($open['item_inventory_id'] ?? null, 'Game strike row is invalid');

        return [
            'kind' => $kind,
            'attackerId' => $attackerId,
            'itemRuleCode' => $itemRuleCode,
            'profileType' => $profileType,
            'profileIndex' => $profileIndex,
            'inventoryId' => $inventoryId,
            'actionRuleCode' => is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '',
        ];
    }

    /**
     * Проверенные поля защитника из строки удара.
     *
     * @param array<string, mixed> $open Строка удара.
     *
     * @return array{kind: string, id: int, profileType: string}
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function defenderOf(array $open): array
    {
        $kind = $this->requireString($open['defender_kind'] ?? null, 'Game strike defender is invalid');
        $id = $this->requireInt($open['defender_id'] ?? null, 'Game strike defender is invalid');
        $profileType = $this->requireString($open['profile_type'] ?? null, 'Game strike defender is invalid');

        return ['kind' => $kind, 'id' => $id, 'profileType' => $profileType];
    }

    /**
     * Авторитетные choices персонажа или NPC.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид.
     * @param int $id Идентификатор.
     *
     * @return array<string, mixed> Choices.
     *
     * @throws GameInvalidException Если документ NPC битый.
     * @throws GameNotFoundException Если участника нет.
     */
    public function participantChoices(GameRecord $game, string $kind, int $id): array
    {
        if ($kind === 'character') {
            return $this->rules->data->characterSnapshot($id)['choices'];
        }

        return $this->npcChoices($game->getId(), $id);
    }

    /**
     * Ключ кэша листа участника.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид.
     * @param int $id Идентификатор.
     *
     * @return string Ключ.
     *
     * @throws GameInvalidException Если NPC чужой.
     * @throws GameNotFoundException Если участника нет.
     */
    public function participantKey(GameRecord $game, string $kind, int $id): string
    {
        if ($kind === 'character') {
            return $this->rules->data->makeParticipantKey(
                'character',
                $id,
                $this->rules->data->characterSnapshot($id)['actualVersion'],
            );
        }

        return $this->rules->data->makeParticipantKey(
            'npc',
            $id,
            $this->sheets->getNpcVersion($game->getId(), $id),
        );
    }

    /**
     * Контекст фильтра атаки для сдвига характеристики.
     *
     * @param GameRecord $game Игра.
     * @param array{kind: string, attackerId: int, itemRuleCode: string, profileType: string, profileIndex: int, inventoryId: int, actionRuleCode: string} $row Поля атакующего.
     *
     * @return array<string, mixed> Контекст.
     *
     * @throws GameInvalidException Если лист битый.
     * @throws GameNotFoundException Если участника нет.
     */
    public function attackFilterContext(GameRecord $game, array $row): array
    {
        return $this->rules->outcome->attackFilterContext(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $row['itemRuleCode'],
            $row['profileType'],
            $row['profileIndex'],
            $this->participantChoices($game, $row['kind'], $row['attackerId']),
            $row['inventoryId'],
        );
    }

    /**
     * Сдвиг характеристики действия для дальнего боя.
     *
     * @param GameRecord $game Игра.
     * @param array{kind: string, attackerId: int, itemRuleCode: string, profileType: string, profileIndex: int, inventoryId: int, actionRuleCode: string} $row Поля атакующего.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return int Сдвиг.
     *
     * @throws GameInvalidException Если лист битый.
     * @throws GameNotFoundException Если участника нет.
     */
    public function actionCharacteristicModifier(GameRecord $game, array $row, CharacterRuleSlice $slice): int
    {
        return $this->rules->modifiers->actionCharacteristicModifier(
            $slice,
            $row['actionRuleCode'],
            $row['profileType'],
            1,
            $this->attackFilterContext($game, $row),
            $this->abilityLevels($game, $row['kind'], $row['attackerId']),
        );
    }

    /**
     * Сдвиг характеристики по номеру попадания и успеху.
     *
     * @param GameRecord $game Игра.
     * @param array{kind: string, attackerId: int, itemRuleCode: string, profileType: string, profileIndex: int, inventoryId: int, actionRuleCode: string} $row Поля атакующего.
     * @param CharacterRuleSlice $slice Срез.
     * @param int $hitNumber Номер попадания с единицы.
     * @param int|null $success Рейтинг успеха.
     *
     * @return int Сдвиг.
     *
     * @throws GameInvalidException Если лист битый.
     * @throws GameNotFoundException Если участника нет.
     */
    public function attackCharacteristicModifier(
        GameRecord $game,
        array $row,
        CharacterRuleSlice $slice,
        int $hitNumber,
        ?int $success,
    ): int {
        return $this->rules->modifiers->attackCharacteristicModifier(
            $slice,
            $row['actionRuleCode'],
            $row['profileType'],
            $hitNumber,
            $this->attackFilterContext($game, $row),
            $this->abilityLevels($game, $row['kind'], $row['attackerId']),
            $success,
        );
    }

    /**
     * Уровни способностей актора.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид актора.
     * @param int $actorId Идентификатор.
     *
     * @return array<string, int> Уровни.
     *
     * @throws GameInvalidException Если лист битый.
     * @throws GameNotFoundException Если листа нет.
     */
    public function abilityLevels(GameRecord $game, string $kind, int $actorId): array
    {
        $sheet = $kind === 'character'
            ? $this->rules->data->characterSnapshot($actorId)['sheet']
            : $this->sheets->getNpcSheet($game->getId(), $actorId);
        $levels = $sheet['abilityLevels'] ?? null;

        return is_array($levels) ? $levels : [];
    }

    /**
     * Требует строковое поле строки удара.
     *
     * @param mixed $value Значение.
     * @param string $message Сообщение об ошибке.
     *
     * @return string Строка.
     *
     * @throws GameInvalidException Если значение не строка.
     */
    private function requireString(mixed $value, string $message): string
    {
        if (!is_string($value)) {
            throw new GameInvalidException($message);
        }

        return $value;
    }

    /**
     * Требует целое поле строки удара.
     *
     * @param mixed $value Значение.
     * @param string $message Сообщение об ошибке.
     *
     * @return int Целое.
     *
     * @throws GameInvalidException Если значение не целое.
     */
    private function requireInt(mixed $value, string $message): int
    {
        if (!is_int($value)) {
            throw new GameInvalidException($message);
        }

        return $value;
    }

    /**
     * Документ choices NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Документ.
     *
     * @throws GameInvalidException Если строка чужая или документа нет.
     * @throws GameNotFoundException Если строки нет.
     */
    private function npcChoices(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        $choices = $npc->getGameId() === $gameId ? ($npc->getVersion()['choices'] ?? null) : null;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game strike npc choices are missing');
        }

        return $choices;
    }
}
