<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterCombatLayer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterCombatLayers;
use Mifrial\Roleplay\Game\Dto\GameCheckThrowOverride;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\DamageTypeSpec;
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
     * @param ICharacterCombatLayers $layers Слои сопротивления.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCheckRoll $roll,
        private readonly GameCheckChain $chain,
        private readonly ICharacterCombatLayers $layers,
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
        $roll = $this->roll->throwWithDifficulty(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            (string) $prepared->path->getCastCheckCode(),
            $prepared->casterSheet,
            ['base' => $base->getBase(), 'size' => $base->getSize()],
            new GameCheckThrowOverride(
                characteristicCode: $characteristic,
                advantages: $prepared->upgrades->getAdvantages(),
            ),
        );

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
     *
     * @throws GameInvalidException Если тип урона битый.
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
     * Сопротивление к сложности по флагу типа урона.
     *
     * @param GameRecord $game Игра.
     * @param GameSpellCastPrepared $prepared Подготовка.
     *
     * @return int Сопротивление или 0.
     *
     * @throws GameInvalidException Если тип урона битый.
     */
    private function difficultyResistance(GameRecord $game, GameSpellCastPrepared $prepared): int
    {
        $target = $prepared->target;
        $damage = $prepared->spell->getSpell()->getDamage();
        if ($target === null || $damage === null) {
            return 0;
        }

        $damageType = $damage->getDamageTypeCode();
        $rule = $prepared->slice->findLive($damageType);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof DamageTypeSpec) {
            throw new GameInvalidException('Game spell damage type is invalid');
        }

        if (!$spec->isModifiesSpellDifficulty()) {
            return 0;
        }

        $total = $this->layerResistance($game, $prepared, $damageType);
        $penetration = $prepared->upgrades->getResistancePenetration();

        return max(0, $total - max(0, $penetration));
    }

    /**
     * Целое сопротивление типу по нетто источников.
     *
     * @param GameRecord $game Игра.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param string $damageType Тип урона.
     *
     * @return int Сопротивление.
     */
    private function layerResistance(GameRecord $game, GameSpellCastPrepared $prepared, string $damageType): int
    {
        $target = $prepared->target;
        if ($target === null) {
            return 0;
        }

        $layers = $this->layers->project(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $target['sheet'],
            $target['choices'],
        );

        $bonus = [];
        $penalty = [];
        $open = [];
        foreach ($layers as $layer) {
            if ($layer->getKind() !== 'resistance' || $layer->getDamageTypeCode() !== $damageType) {
                continue;
            }

            $this->keepLayer($bonus, $penalty, $open, $layer);
        }

        $total = 0;
        foreach (array_merge($open, array_values($bonus), array_values($penalty)) as $layer) {
            $total += $layer->getValue()->toInteger();
        }

        return $total;
    }

    /**
     * Лучший бонус и худший штраф одного источника.
     *
     * @param array<string, CharacterCombatLayer> $bonus Бонусы.
     * @param array<string, CharacterCombatLayer> $penalty Штрафы.
     * @param list<CharacterCombatLayer> $open Слои без источника.
     * @param CharacterCombatLayer $layer Слой.
     *
     * @return void
     */
    private function keepLayer(
        array &$bonus,
        array &$penalty,
        array &$open,
        CharacterCombatLayer $layer,
    ): void {
        $source = $layer->getSourceCode();
        if ($source === '') {
            $open[] = $layer;

            return;
        }

        $value = $layer->getValue()->toInteger();
        if ($value > 0 && (!isset($bonus[$source]) || $value > $bonus[$source]->getValue()->toInteger())) {
            $bonus[$source] = $layer;
        }

        if ($value < 0 && (!isset($penalty[$source]) || $value < $penalty[$source]->getValue()->toInteger())) {
            $penalty[$source] = $layer;
        }
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
