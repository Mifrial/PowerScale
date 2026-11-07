<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameStrikeActorRef;
use Mifrial\Roleplay\Game\Dto\GameStrikeDefenseTarget;
use Mifrial\Roleplay\Game\Dto\GameStrikeProfileRef;
use Mifrial\Roleplay\Game\Dto\GameStrikeTarget;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Снимки участников и запросы удара/защиты.
 */
final class GameStrikeResolutionActors
{
    /**
     * Создаёт сборку запросов.
     *
     * @param GameNpcRepository $npcs NPC.
     *
     * @return void
     */
    public function __construct(
        private readonly GameNpcRepository $npcs,
    ) {
    }

    /**
     * Запрос удара по проверенной строке.
     *
     * @param GameRecord $game Игра.
     * @param array{kind: string, attackerId: int, itemRuleCode: string, profileType: string, profileIndex: int, inventoryId: int, actionRuleCode: string} $row Поля атакующего.
     * @param int|null $characteristicModifier Сдвиг характеристики.
     * @param int $strengthDrop Штраф силы броска в размерах.
     * @param int $gripBonus Бонус удержания.
     *
     * @return GameStrikeTarget Запрос.
     *
     * @throws GameInvalidException Если снимок NPC битый.
     * @throws GameNotFoundException Если NPC нет.
     */
    public function target(
        GameRecord $game,
        array $row,
        ?int $characteristicModifier = null,
        int $strengthDrop = 0,
        int $gripBonus = 0,
    ): GameStrikeTarget {
        $npc = $row['kind'] === 'npc' ? $this->npcData($game->getId(), $row['attackerId']) : null;

        return GameStrikeTarget::of(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $row['itemRuleCode'],
            $row['profileType'],
            $row['profileIndex'],
            $row['inventoryId'],
            $row['kind'],
            $row['attackerId'],
            $npc['sheet'] ?? null,
            $npc['choices'] ?? null,
            $npc['version'] ?? null,
            $characteristicModifier,
            $strengthDrop,
            $gripBonus,
        );
    }

    /**
     * Запрос защиты по строке и выбранной реакции.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид защитника.
     * @param int $id Идентификатор.
     * @param string $itemRuleCode Код предмета.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Индекс профиля.
     * @param string $reaction Реакция.
     * @param int|null $blockItemInventoryId Строка блока.
     * @param int $rating Рейтинг.
     * @param DimensionalNumber|null $penetration Проникновение.
     * @param int $durabilityShave Срез надёжности.
     * @param int|null $npcVersion Версия листа NPC.
     *
     * @return GameStrikeDefenseTarget Запрос.
     *
     * @throws GameInvalidException Если снимок NPC битый.
     * @throws GameNotFoundException Если NPC нет.
     */
    public function defenseTarget(
        GameRecord $game,
        string $kind,
        int $id,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        string $reaction,
        ?int $blockItemInventoryId,
        int $rating,
        ?DimensionalNumber $penetration,
        int $durabilityShave,
        ?int $npcVersion = null,
    ): GameStrikeDefenseTarget {
        return new GameStrikeDefenseTarget(
            new GameStrikeProfileRef(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $itemRuleCode,
                $profileType,
                $profileIndex,
                0,
            ),
            $this->actorRef($game, $kind, $id, $npcVersion),
            $reaction, $blockItemInventoryId, $rating, $penetration, $durabilityShave,
        );
    }

    /**
     * Снимок листа и choices NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array{sheet: array<string, mixed>, choices: array<string, mixed>, version: int}
     *
     * @throws GameInvalidException Если снимок битый.
     * @throws GameNotFoundException Если NPC чужой.
     */
    public function npcData(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        if ($npc->getGameId() !== $gameId) {
            throw new GameNotFoundException('Game strike npc was not found');
        }

        $version = $npc->getVersion();
        $sheet = $version['sheet'] ?? null;
        $choices = $version['choices'] ?? null;
        if (!is_array($sheet) || !is_array($choices)) {
            throw new GameInvalidException('Game strike npc sheet is missing');
        }

        return ['sheet' => $sheet, 'choices' => $choices, 'version' => $npc->getActualVersion()];
    }

    /**
     * Ссылка на участника защиты.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид участника.
     * @param int $id Идентификатор.
     * @param int|null $npcVersion Версия листа NPC.
     *
     * @return GameStrikeActorRef Ссылка.
     *
     * @throws GameInvalidException Если снимок NPC битый.
     * @throws GameNotFoundException Если NPC нет.
     */
    private function actorRef(GameRecord $game, string $kind, int $id, ?int $npcVersion): GameStrikeActorRef
    {
        $npc = $kind === 'npc' ? $this->npcData($game->getId(), $id) : null;

        return new GameStrikeActorRef(
            $kind,
            $id,
            $npc['sheet'] ?? null,
            $npc['choices'] ?? null,
            $npcVersion,
        );
    }
}
