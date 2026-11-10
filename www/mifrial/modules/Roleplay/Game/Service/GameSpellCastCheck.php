<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameCheckThrowOverride;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Сложность сотворения и серверный бросок проверки.
 */
final class GameSpellCastCheck
{
    private const BASE_DIFFICULTY = [3, 0];

    private const MIN_LIVE_DIFFICULTY = [3, -1];

    private const SKIP_DIFFICULTY = [0, -1];

    /**
     * Создаёт расчёт сложности.
     *
     * @param GameCheckRoll $roll Бросок.
     * @param GameCheckChain $chain Цепочка проверок.
     * @param GameSpellMagicResistance $magicResistance Сопротивление магии.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCheckRoll $roll,
        private readonly GameCheckChain $chain,
        private readonly GameSpellMagicResistance $magicResistance,
    ) {
    }

    /**
     * Сложность, необходимость броска и сам бросок.
     *
     * @param GameRecord $game Игра.
     * @param GameSpellCastPrepared $prepared Подготовка.
     *
     * @return array{
     *     difficulty: array{base: int, size: int},
     *     needsCheck: bool,
     *     roll: array<string, mixed>|null,
     *     castOk: bool
     * } Итог проверки.
     *
     * @throws GameInvalidException Если проверка или характеристика битые.
     */
    public function check(GameRecord $game, GameSpellCastPrepared $prepared): array
    {
        $difficulty = $this->difficulty($game, $prepared);
        $base = new CharacteristicNumber($difficulty->getBase(), $difficulty->getSize());
        $min = new CharacteristicNumber(self::MIN_LIVE_DIFFICULTY[0], self::MIN_LIVE_DIFFICULTY[1]);
        if ($base->characteristicDiffTo($min) < 0) {
            return [
                'difficulty' => ['base' => self::SKIP_DIFFICULTY[0], 'size' => self::SKIP_DIFFICULTY[1]],
                'needsCheck' => false,
                'roll' => null,
                'castOk' => true,
            ];
        }

        $characteristic = $this->characteristic($prepared);
        $efficiency = $prepared->efficiency;
        $value = $efficiency->getEfficiency();
        $explicit = $efficiency->isExplicit();
        $roll = $this->roll->throwWithDifficulty(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            (string) $prepared->path->getCastCheckCode(),
            $prepared->casterSheet,
            ['base' => $base->getBase(), 'size' => $base->getSize()],
            new GameCheckThrowOverride(
                characteristicCode: $characteristic,
                efficiency: $explicit ? $value->getBase() : null,
                advantages: $prepared->upgrades->getAdvantages(),
                efficiencySize: $explicit ? $value->getSize() : null,
            ),
            true,
        );
        if ($efficiency->getAdjustments() !== []) {
            $roll['efficiencyAdjustments'] = $efficiency->getAdjustments();
        }

        return [
            'difficulty' => ['base' => $base->getBase(), 'size' => $base->getSize()],
            'needsCheck' => true,
            'roll' => $roll,
            'castOk' => $roll['success'] === true,
        ];
    }

    /**
     * Сложность до броска: дефицит мощи и контроля, сопротивление.
     *
     * @param GameRecord $game Игра.
     * @param GameSpellCastPrepared $prepared Подготовка.
     *
     * @return CharacteristicNumber Сложность.
     */
    private function difficulty(GameRecord $game, GameSpellCastPrepared $prepared): CharacteristicNumber
    {
        $requiredPower = new CharacteristicNumber(
            $prepared->requiredPower->getBase(),
            $prepared->requiredPower->getSize(),
        );
        $requiredControl = new CharacteristicNumber(
            $prepared->requiredControl->getBase(),
            $prepared->requiredControl->getSize(),
        );

        $powerShortage = $requiredPower->characteristicDiffTo($prepared->usedPower);
        $controlShortage = $requiredControl->characteristicDiffTo($prepared->availableControl);
        $delta = 0;
        if ($powerShortage > 0) {
            $delta += $powerShortage;
        }

        if ($controlShortage > 0) {
            $delta += $controlShortage;
        }

        if ($powerShortage < 0 && $controlShortage < 0) {
            $delta -= min(-$powerShortage, -$controlShortage);
        }

        $difficulty = (new CharacteristicNumber(self::BASE_DIFFICULTY[0], self::BASE_DIFFICULTY[1]))
            ->modify($delta);
        $resistance = $this->difficultyResistance($game, $prepared);
        if ($resistance > 0) {
            $difficulty = $difficulty->modify($resistance);
        }

        return $difficulty;
    }

    /**
     * Сопротивление к сложности по магическим типам урона.
     *
     * @param GameRecord $game Игра.
     * @param GameSpellCastPrepared $prepared Подготовка.
     *
     * @return int Сопротивление или 0.
     *
     * @throws GameInvalidException Если тип или слой битые.
     */
    private function difficultyResistance(GameRecord $game, GameSpellCastPrepared $prepared): int
    {
        $target = $prepared->target;
        if ($target === null) {
            return 0;
        }

        $total = $this->magicResistance->sumResistance(
            $game,
            $prepared->slice,
            $target['sheet'],
            $target['choices'],
        );
        $penetration = $prepared->upgrades->getResistancePenetration();

        return max(0, $total - max(0, $penetration));
    }

    /**
     * Характеристика проверки из path.check_code.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     *
     * @return string Код.
     *
     * @throws GameInvalidException Если характеристика не найдена.
     */
    private function characteristic(GameSpellCastPrepared $prepared): string
    {
        $code = (string) $prepared->path->getCheckCode();
        $rule = $prepared->slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if ($spec instanceof CharacteristicSpec) {
            return $code;
        }

        $check = $this->chain->checkOf($prepared->slice, $code, true);
        $chain = $this->chain->chain($prepared->slice, $check, $code, true);
        $characteristic = $chain['characteristic'];
        if (!is_string($characteristic) || $characteristic === '') {
            throw new GameInvalidException('Game spell check characteristic is invalid');
        }

        return $characteristic;
    }
}
