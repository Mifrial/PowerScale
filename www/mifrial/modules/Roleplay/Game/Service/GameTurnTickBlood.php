<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

/**
 * Тик крови: накопление, истощение и увечье.
 */
final class GameTurnTickBlood
{
    /**
     * Создаёт тик крови.
     *
     * @param GameStrikeConsequences $consequences Проверки и урон.
     * @param GameTurnSheetStates $states Строки листа.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeConsequences $consequences,
        private readonly GameTurnSheetStates $states,
    ) {
    }

    /**
     * Кровопотеря от ран и её последствия.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param int $tick Приток крови за ход.
     *
     * @return array{operations: list<array<string, mixed>>, rolls: list<array{roll: array<string, mixed>, text: string}>} Итог.
     *
     * @throws GameInvalidException Если состояний крови или истощения не по одной.
     */
    public function tick(GameRecord $game, CharacterRuleSlice $slice, array $sheet, int $tick): array
    {
        if ($tick <= 0) {
            return ['operations' => [], 'rolls' => []];
        }

        $code = $this->oneFlag($slice, 'blood');
        $operations = [['kind' => 'putState', 'stateRuleCode' => $code, 'value' => $tick]];
        $rolls = [['roll' => ['base' => $tick, 'size' => 0], 'text' => 'Кровь']];
        $total = $this->states->stored($sheet, $code) + $tick;
        $exhaustion = $this->oneFlag($slice, 'exhaustion');
        $delta = max(0, intdiv($total, 10) - $this->states->stored($sheet, $exhaustion));
        if ($delta > 0) {
            $will = $this->will($game, $slice, $sheet, $exhaustion, $delta);
            $operations = [...$operations, ...$will['operations']];
            $rolls = [...$rolls, ...$will['rolls']];
        }

        if ($total >= 40) {
            $maim = $this->maim($game, $sheet, $total);
            $operations = [...$operations, ...$maim['operations']];
            $rolls = [...$rolls, ...$maim['rolls']];
        }

        return ['operations' => $operations, 'rolls' => $rolls];
    }

    /**
     * Истощение и проверка воли.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param string $exhaustion Код истощения.
     * @param int $delta Прирост.
     *
     * @return array{operations: list<array<string, mixed>>, rolls: list<array{roll: array<string, mixed>, text: string}>} Итог.
     */
    private function will(
        GameRecord $game,
        CharacterRuleSlice $slice,
        array $sheet,
        string $exhaustion,
        int $delta,
    ): array {
        $will = $this->consequences->willForDelta($game, $sheet, $delta);
        $operations = [$this->exhaustionWrite($slice, $sheet, $exhaustion, $delta), ...$will['operations']];
        $rolls = $will['roll'] !== null ? [['roll' => $will['roll'], 'text' => 'Воля']] : [];

        return ['operations' => $operations, 'rolls' => $rolls];
    }

    /**
     * Увечье от накопленной крови.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $sheet Лист.
     * @param int $total Кровь после притока.
     *
     * @return array{operations: list<array<string, mixed>>, rolls: list<array{roll: array<string, mixed>, text: string}>} Итог.
     */
    private function maim(GameRecord $game, array $sheet, int $total): array
    {
        $difficulty = 2 ** intdiv($total - 40, 10);
        $maim = $this->consequences->maimRoll($game, $sheet, $difficulty);

        return [
            'operations' => [[
                'kind' => 'putState',
                'stateRuleCode' => $this->consequences->singleState($game, 'maim'),
                'value' => $maim['rating'],
            ]],
            'rolls' => [['roll' => $maim, 'text' => 'Увечье крови']],
        ];
    }

    /**
     * Запись истощения: независимое состояние или обычное.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Код истощения.
     * @param int $delta Прирост.
     *
     * @return array<string, mixed> Операция.
     */
    private function exhaustionWrite(CharacterRuleSlice $slice, array $sheet, string $code, int $delta): array
    {
        $rule = $slice->findLive($code);
        $spec = $rule instanceof CharacterResolvedRule ? $rule->getSpec() : null;
        $exists = $this->states->stored($sheet, $code) > 0 || $this->states->hasRow($sheet, $code);
        if ($spec instanceof StateSpec && $spec->getAggregation() === 'independent' && $exists) {
            return [
                'kind' => 'putDamageSplit',
                'remainder' => ['base' => $this->states->remainder($slice, $sheet), 'size' => 0],
                'quotient' => $delta,
            ];
        }

        return ['kind' => 'putState', 'stateRuleCode' => $code, 'value' => $delta];
    }

    /**
     * Ровно одна карточка с флагом.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $flag blood или exhaustion.
     *
     * @return string Код карточки.
     *
     * @throws GameInvalidException Если карточек не одна.
     */
    private function oneFlag(CharacterRuleSlice $slice, string $flag): string
    {
        $matched = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if (!$spec instanceof StateSpec) {
                continue;
            }

            $hit = ($flag === 'blood' && $spec->isBloodLoss()) || ($flag === 'exhaustion' && $spec->isDamageExhaustion());
            if ($hit) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) !== 1) {
            throw new GameInvalidException('Game turn state is invalid');
        }

        return $matched[0];
    }
}
