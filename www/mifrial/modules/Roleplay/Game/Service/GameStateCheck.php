<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;

/**
 * Бросок проверки прироста состояния.
 *
 * Связку «состояние -> проверка -> исход» читает GameStateIncreaseRule.
 * Здесь только кубик против значения после прироста и выбор исхода.
 */
final class GameStateCheck
{
    /**
     * Создаёт проверку.
     *
     * @param GameCheckRoll $checks Бросок.
     * @param GameStateIncreaseRule $rule Связка из спеки.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCheckRoll $checks,
        private readonly GameStateIncreaseRule $rule,
    ) {
    }

    /**
     * Карточка проверки, объявленная состоянием.
     *
     * @param CharacterRuleSlice $slice Срез листа.
     * @param string $stateCode Код состояния.
     * @param string $checkCode Код проверки.
     *
     * @return CheckSpec Карточка.
     *
     * @throws GameInvalidException Если связка битая.
     */
    public function check(CharacterRuleSlice $slice, string $stateCode, string $checkCode): CheckSpec
    {
        return $this->rule->check($slice, $stateCode, $checkCode);
    }

    /**
     * Состояние исхода по рейтингу или null.
     *
     * @param CharacterRuleSlice $slice Срез листа.
     * @param CheckSpec $check Карточка проверки.
     * @param int $rating Рейтинг.
     *
     * @return string|null Код состояния или null.
     *
     * @throws GameInvalidException Если состояние исхода не живое.
     */
    public function outcome(CharacterRuleSlice $slice, CheckSpec $check, int $rating): ?string
    {
        return $this->rule->outcome($slice, $check, $rating);
    }

    /**
     * Бросок проверки против значения состояния после прироста.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $sheet Лист.
     * @param CharacterRuleSlice $slice Срез листа.
     * @param string $stateCode Код состояния.
     * @param string $checkCode Код проверки.
     * @param int $after Значение после прироста.
     *
     * @return array{roll: array<string, mixed>, outcome: string|null} Бросок и исход.
     *
     * @throws GameInvalidException Если связка битая.
     */
    public function roll(
        GameRecord $game,
        array $sheet,
        CharacterRuleSlice $slice,
        string $stateCode,
        string $checkCode,
        int $after,
    ): array {
        $check = $this->rule->check($slice, $stateCode, $checkCode);
        $roll = $this->checks->throwWithDifficulty(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $checkCode,
            $sheet,
            ['base' => $after, 'size' => 0],
        );
        $roll['label'] = $slice->findLive($checkCode)?->getName() ?? $checkCode;
        $rating = is_int($roll['rating'] ?? null) ? $roll['rating'] : 0;

        return ['roll' => $roll, 'outcome' => $this->rule->outcome($slice, $check, $rating)];
    }
}
