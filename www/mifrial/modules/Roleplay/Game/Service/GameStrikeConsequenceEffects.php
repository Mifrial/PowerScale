<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Сборка операций последствий удара: рана, истощение, Воля, нокаут, увечье.
 */
final class GameStrikeConsequenceEffects
{
    /**
     * Создаёт сборку последствий.
     *
     * @param GameStrikeRules $rules Срез удара.
     * @param GameStrikeConsequenceStates $states Состояния.
     * @param GameStrikeConsequenceRules $calc Расчёты урона.
     * @param GameStrikeConsequenceWill $will Проверки Воли и увечья.
     * @param GameStateDecline $decline Шкала decline.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeConsequenceStates $states,
        private readonly GameStrikeConsequenceRules $calc,
        private readonly GameStrikeConsequenceWill $will,
        private readonly GameStateDecline $decline,
    ) {
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
    public function build(
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
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $hooks = $damageTypeCode === null ? [] : $this->states->hooks($slice, $damageTypeCode);
        $cutting = $this->calc->cutting($hooks);
        $quotient = $split['quotient'];
        $wound = $cutting === null ? 0 : $this->calc->woundStrength($slice, $sheet, $injury);
        $will = $this->willEffect($game, $slice, $sheet, $quotient, $cutting);
        $knockoutEffect = $this->knockoutEffect(
            $hooks,
            $success,
            $injury,
            $slice,
            $sheet,
            $endurance,
            $knockout,
            $will['outcome'],
        );
        $maim = $this->maimEffect($game, $slice, $sheet, $success, $hooks, $cutting ? 0 : $quotient, $wound);
        $consequence = $this->consequence(
            $cutting,
            $quotient,
            $split,
            $endurance,
            $wound,
            $will['consequence'],
            $knockoutEffect['unconscious'],
            $maim['maim'],
        );
        $delta = $cutting === null ? $quotient : 0;
        $consequence['exhaustionTotal'] = $this->exhaustionTotal($slice, $sheet, $delta);
        $consequence['states'] = $this->hookStates($slice, $hooks, $quotient);

        return [
            'operations' => $this->operations(
                $split,
                $hooks,
                $quotient,
                $cutting,
                $wound,
                $will,
                $knockoutEffect,
                $maim,
                $internal,
            ),
            'consequence' => $consequence,
        ];
    }

    /**
     * Итоговое истощение цели после прироста.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист цели.
     * @param int $delta Прирост истощения.
     *
     * @return int Итог.
     *
     * @throws GameInvalidException Если состояние истощения битое.
     */
    private function exhaustionTotal(CharacterRuleSlice $slice, array $sheet, int $delta): int
    {
        return $this->states->stored($sheet, $this->states->single($slice, 'exhaustion')) + $delta;
    }

    /**
     * Состояния от хуков типа урона (например, Шок).
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param list<array{handler: IMechanicHandler, payload: array<string, mixed>}> $hooks Хуки.
     * @param int $quotient Частное урона.
     *
     * @return list<array{code: string, name: string, value: int}> Состояния.
     *
     * @throws GameInvalidException Если состояние хука битое.
     */
    private function hookStates(CharacterRuleSlice $slice, array $hooks, int $quotient): array
    {
        $states = [];
        foreach ($hooks as $hook) {
            $amount = $this->states->exhaustion($hook['handler'], $hook['payload'], $quotient);
            if ($amount === null || $amount === 0) {
                continue;
            }

            $code = $this->states->code($hook['payload']);
            $name = $slice->findLive($code)?->getName();
            $states[$code] = [
                'code' => $code,
                'name' => is_string($name) && $name !== '' ? $name : $code,
                'value' => ($states[$code]['value'] ?? 0) + $amount,
            ];
        }

        return array_values($states);
    }

    /**
     * Склеивает операции урона, истощения, Воли, нокаута и увечья.
     *
     * @param array{kind: string, remainder: array{base: int, size: int}, quotient: int} $split Деление урона.
     * @param list<array{handler: IMechanicHandler, payload: array<string, mixed>}> $hooks Хуки.
     * @param int $quotient Частное урона.
     * @param string|null $cutting Код раны.
     * @param int $wound Сила раны.
     * @param array{operations: list<array<string, mixed>>} $will Операции Воли.
     * @param array{operations: list<array<string, mixed>>} $knockoutEffect Операции нокаута.
     * @param array{operations: list<array<string, mixed>>} $maim Операции увечья.
     * @param bool $internal Внутренняя ли рана.
     *
     * @return list<array<string, mixed>> Операции.
     *
     * @throws GameInvalidException Если хук битый.
     */
    private function operations(
        array $split,
        array $hooks,
        int $quotient,
        ?string $cutting,
        int $wound,
        array $will,
        array $knockoutEffect,
        array $maim,
        bool $internal = false,
    ): array {
        return array_merge(
            $this->damageOperations($split, $cutting, $wound, $internal),
            $this->exhaustionOperations($hooks, $quotient),
            $will['operations'],
            $knockoutEffect['operations'],
            $maim['operations'],
        );
    }

    /**
     * Операции раны и обычного урона.
     *
     * @param array{kind: string, remainder: array{base: int, size: int}, quotient: int} $split Деление урона.
     * @param string|null $cutting Код раны.
     * @param int $wound Сила раны.
     * @param bool $internal Внутренняя ли рана.
     *
     * @return list<array<string, mixed>> Операции.
     */
    private function damageOperations(array $split, ?string $cutting, int $wound, bool $internal = false): array
    {
        $operations = $cutting ? [] : [$split];
        if ($wound > 0 && $cutting !== null) {
            $operations[] = [
                'kind' => 'putState',
                'stateRuleCode' => $cutting,
                'value' => $wound,
                'bandage' => 0,
                'clotting' => 0,
                'internal' => $internal,
                'aided' => false,
                'heldBy' => null,
            ];
        }

        return $operations;
    }

    /**
     * Операции истощения по хукам механик.
     *
     * @param list<array{handler: IMechanicHandler, payload: array<string, mixed>}> $hooks Хуки.
     * @param int $quotient Частное урона.
     *
     * @return list<array<string, mixed>> Операции.
     *
     * @throws GameInvalidException Если хук битый.
     */
    private function exhaustionOperations(array $hooks, int $quotient): array
    {
        $operations = [];
        foreach ($hooks as $hook) {
            $amount = $this->states->exhaustion($hook['handler'], $hook['payload'], $quotient);
            if ($amount === null || $amount === 0) {
                continue;
            }

            $operations[] = [
                'kind' => 'putState',
                'stateRuleCode' => $this->states->code($hook['payload']),
                'value' => $amount,
            ];
        }

        return $operations;
    }

    /**
     * Проверка Воли при обычном уроне без сознания.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист.
     * @param int $quotient Частное урона.
     * @param string|null $cutting Код раны.
     *
     * @return array{
     *     operations: list<array<string, mixed>>,
     *     consequence: array<string, mixed>,
     *     outcome: string|null
     * }
     *
     * @throws GameInvalidException Если проверка Воли битая.
     */
    private function willEffect(
        GameRecord $game,
        CharacterRuleSlice $slice,
        array $sheet,
        int $quotient,
        ?string $cutting,
    ): array {
        $wasUnconscious = $this->states->has($slice, $sheet, 'unconscious');
        if ($cutting !== null || $quotient === 0 || $wasUnconscious) {
            return ['operations' => [], 'consequence' => [], 'outcome' => null];
        }

        $will = $this->will->will($game, $slice, $sheet, $quotient);
        $operations = $this->decline->operations(
            $sheet,
            $slice,
            $will['operation'],
            $will['declineCodes'],
        );

        return [
            'operations' => $operations,
            'consequence' => ['will' => $will['roll'], 'willOutcome' => $will['outcome']],
            'outcome' => $will['outcome'],
        ];
    }

    /**
     * Нокаут от тупого урона, если Воля не выбила сознание.
     *
     * @param list<array{handler: IMechanicHandler, payload: array<string, mixed>}> $hooks Хуки.
     * @param int $success РУ.
     * @param DimensionalNumber $injury Повреждение.
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист.
     * @param DimensionalNumber|null $endurance Стойкость.
     * @param bool $knockout Нокаут.
     * @param string|null $willOutcome Исход проверки Воли.
     *
     * @return array{operations: list<array<string, mixed>>, unconscious: bool}
     *
     * @throws GameInvalidException Если состояние без сознания битое.
     */
    private function knockoutEffect(
        array $hooks,
        int $success,
        DimensionalNumber $injury,
        CharacterRuleSlice $slice,
        array $sheet,
        ?DimensionalNumber $endurance,
        bool $knockout,
        ?string $willOutcome,
    ): array {
        if (
            !$knockout || $willOutcome === 'unconscious' || !$this->calc->blunt(
                $hooks,
                $success,
                $injury,
                $slice,
                $sheet,
                $endurance,
            )
        ) {
            return ['operations' => [], 'unconscious' => false];
        }

        $code = $this->states->single($slice, 'unconscious');
        $operations = $this->decline->operations(
            $sheet,
            $slice,
            ['kind' => 'replaceDecline', 'stateRuleCode' => $code],
        );

        return ['operations' => $operations, 'unconscious' => true];
    }

    /**
     * Базовый итог последствий с волей и нокаутом.
     *
     * @param string|null $cutting Код раны.
     * @param int $quotient Частное урона.
     * @param array{kind: string, remainder: array{base: int, size: int}, quotient: int} $split Деление урона.
     * @param DimensionalNumber|null $endurance Стойкость.
     * @param int $wound Сила раны.
     * @param array<string, mixed> $willConsequence Итог Воли.
     * @param bool $unconscious Нокаут.
     * @param array<string, mixed>|null $maim Увечье.
     *
     * @return array<string, mixed> Итог.
     */
    private function consequence(
        ?string $cutting,
        int $quotient,
        array $split,
        ?DimensionalNumber $endurance,
        int $wound,
        array $willConsequence,
        bool $unconscious,
        ?array $maim,
    ): array {
        $consequence = ['wound' => $wound, 'exhaustionDelta' => $cutting ? 0 : $quotient];
        if ($cutting === null) {
            $consequence['damageRemainder'] = $split['remainder'];
        }

        if ($endurance instanceof DimensionalNumber) {
            $consequence['endurance'] = ['base' => $endurance->getBase(), 'size' => $endurance->getSize()];
        }

        $consequence += $willConsequence;
        if ($unconscious) {
            $consequence['unconscious'] = true;
        }

        if ($maim !== null) {
            $consequence['maim'] = $maim;
        }

        return $consequence;
    }

    /**
     * Увечье по истощению и силе раны.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист.
     * @param int $success РУ.
     * @param list<array{handler: IMechanicHandler, payload: array<string, mixed>}> $hooks Хуки.
     * @param int $delta Изменение истощения.
     * @param int $wound Сила раны.
     *
     * @return array{operations: list<array<string, mixed>>, maim: array<string, mixed>|null}
     *
     * @throws GameInvalidException Если проверка увечья битая.
     */
    private function maimEffect(
        GameRecord $game,
        CharacterRuleSlice $slice,
        array $sheet,
        int $success,
        array $hooks,
        int $delta,
        int $wound,
    ): array {
        if ($delta === 0 && $wound <= 0) {
            return ['operations' => [], 'maim' => null];
        }

        $difficulty = max($delta, intdiv($wound, 2)) + $this->calc->getInjuryExtra($hooks, $success);
        $maim = $this->will->maim($game, $slice, $sheet, $difficulty, $hooks);
        $strength = is_int($maim['rating'] ?? null) && $maim['rating'] < 0 ? -$maim['rating'] : 0;
        $operations = [];
        if ($strength > 0) {
            $operations[] = [
                'kind' => 'putState',
                'stateRuleCode' => $this->states->single($slice, 'maim'),
                'value' => $strength,
                'maim' => $this->calc->maimSidecar($strength),
            ];
        }

        return ['operations' => $operations, 'maim' => $maim];
    }
}
