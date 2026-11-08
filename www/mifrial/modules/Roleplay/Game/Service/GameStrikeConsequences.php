<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Операции immediate consequences и чат close. Формулу injury не считает.
 */
final class GameStrikeConsequences
{
    public GameStrikeConsequenceChat $chat;

    /**
     * Собирает последствия удара из ролей.
     *
     * @param GameStrikeRules $rules Срез удара.
     * @param GameStrikeConsequenceStates $states Состояния и флаги.
     * @param GameStrikeConsequenceWill $will Проверки Воли и увечья.
     * @param GameStrikeConsequenceEffects $effect Сборка операций.
     * @param GameStrikeConsequenceChat $chat Запись в чат.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeConsequenceStates $states,
        private readonly GameStrikeConsequenceWill $will,
        private readonly GameStrikeConsequenceEffects $effect,
        GameStrikeConsequenceChat $chat,
    ) {
        $this->chat = $chat;
    }

    /**
     * Список операций цели и узкие исходы бросков.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $sheet Лист цели.
     * @param DimensionalNumber $injury Повреждение.
     * @param int $success РУ.
     * @param array{kind: string, remainder: array{base: int, size: int}, quotient: int} $split Деление обычного урона.
     * @param string|null $damageTypeCode Тип урона.
     * @param bool $knockout Нокаут.
     * @param DimensionalNumber|null $endurance Стойкость.
     * @param bool $internal Внутренняя ли рана.
     *
     * @return array{operations: list<array<string, mixed>>, consequence: array<string, mixed>}
     *
     * @throws GameInvalidException Если карточки среза или броски битые.
     */
    public function effects(
        GameRecord $game,
        array $sheet,
        DimensionalNumber $injury,
        int $success,
        array $split,
        ?string $damageTypeCode,
        bool $knockout = true,
        ?DimensionalNumber $endurance = null,
        bool $internal = false,
    ): array {
        return $this->effect->build(
            $game,
            $sheet,
            $injury,
            $success,
            $split,
            $damageTypeCode,
            $knockout,
            $endurance,
            $internal,
        );
    }

    /**
     * Воля при изменении истощения. Рост без сознания — пустой итог; спад проверяется.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $sheet Лист.
     * @param int $delta Изменение истощения.
     *
     * @return array{
     *     roll: array<string, mixed>|null,
     *     operation: array<string, mixed>|null,
     *     outcome: string|null,
     *     operations: list<array<string, mixed>>,
     *     declineCodes: list<string>
     * }
     */
    public function willForDelta(GameRecord $game, array $sheet, int $delta): array
    {
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());

        return $this->will->forDelta($game, $slice, $sheet, $delta);
    }

    /**
     * Бросок увечья с уже посчитанной трудностью.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $sheet Лист.
     * @param int $difficulty Трудность.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int}
     */
    public function maimRoll(GameRecord $game, array $sheet, int $difficulty): array
    {
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());

        return $this->will->maim($game, $slice, $sheet, $difficulty, []);
    }

    /**
     * Добровольная проверка способности с уже посчитанной трудностью.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $sheet Лист.
     * @param string $checkCode Проверка.
     * @param int $difficulty Сложность.
     *
     * @return array<string, mixed> Бросок.
     *
     * @throws GameInvalidException Если карточка проверки битая.
     */
    public function optionalCheck(GameRecord $game, array $sheet, string $checkCode, int $difficulty): array
    {
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());

        return $this->will->optionalCheck($game, $slice, $sheet, $checkCode, $difficulty);
    }

    /**
     * Код единственной карточки с флагом.
     *
     * @param GameRecord $game Игра.
     * @param string $flag Флаг.
     *
     * @return string Код.
     */
    public function singleState(GameRecord $game, string $flag): string
    {
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());

        return $this->states->single($slice, $flag);
    }

    /**
     * Пишет цепочку в чат игры: действие, нарратив, бросок, итог, Воля, увечье.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param string $threadId Нить.
     * @param bool $announce Писать действие.
     * @param string $action Действие.
     * @param string $strike Нарратив.
     * @param array{base: int, size: int}|array<string, mixed> $hitRoll Бросок попадания.
     * @param string $result Итог.
     * @param array<string, mixed> $calc Payload attack-calc.
     * @param array{roll: array<string, mixed>, text: string}|null $will Проверка истощения.
     * @param array{roll: array<string, mixed>, text: string, details: array<string, mixed>}|null $maim Увечье.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     * @param array<string, mixed>|null $defenseRoll Бросок защиты.
     * @param string|null $parentId Родительский ход.
     * @param array{text: string, roll: array<string, mixed>}|null $beforeOutcome Проверка до итога.
     * @param list<array{text: string, roll: array<string, mixed>}> $preRollChecks Проверки перед общими бросками.
     *
     * @return void
     *
     * @throws GameInvalidException Если чат игры или его участник недоступен.
     */
    public function post(
        GameRecord $game,
        int $actorUserId,
        string $threadId,
        bool $announce,
        string $action,
        string $strike,
        array $hitRoll,
        string $result,
        array $calc,
        ?array $will = null,
        ?array $maim = null,
        ?array $speaker = null,
        ?array $defenseRoll = null,
        ?string $parentId = null,
        ?array $beforeOutcome = null,
        array $preRollChecks = [],
    ): void {
        $this->chat->post(
            $game,
            $actorUserId,
            $threadId,
            $announce,
            $action,
            $strike,
            $hitRoll,
            $result,
            $calc,
            $will,
            $maim,
            $speaker,
            $defenseRoll,
            $parentId,
            $beforeOutcome,
            $preRollChecks,
        );
    }
}
