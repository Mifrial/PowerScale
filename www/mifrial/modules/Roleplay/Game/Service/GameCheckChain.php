<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;

/**
 * Карточки проверок среза: выбор, режим и цепочка предков.
 */
final class GameCheckChain
{
    /**
     * Живая карточка проверки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $ruleCode Код.
     * @param bool $allowLater Разрешить шаг не этого хода.
     *
     * @return CheckSpec Карточка.
     *
     * @throws GameInvalidException Если это не проверка этого шага.
     */
    public function checkOf(CharacterRuleSlice $slice, string $ruleCode, bool $allowLater = false): CheckSpec
    {
        $rule = $slice->findLive($ruleCode);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof CheckSpec) {
            throw new GameInvalidException('Game check rule is invalid');
        }

        if (!$allowLater && $this->isLaterStep($spec)) {
            throw new GameInvalidException('Game check rule is invalid');
        }

        return $spec;
    }

    /**
     * Живая карточка попадания. checkOf её не принимает.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $ruleCode Код.
     *
     * @return CheckSpec Карточка.
     *
     * @throws GameInvalidException Если это не попадание этого шага.
     */
    public function hitSpec(CharacterRuleSlice $slice, string $ruleCode): CheckSpec
    {
        $rule = $slice->findLive($ruleCode);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        $blocked = $spec instanceof CheckSpec ? $this->hitBlocked($spec) : true;
        if (
            !$spec instanceof CheckSpec
            || (!$spec->isHitCheck() && !$spec->isPushCheck())
            || $blocked
        ) {
            throw new GameInvalidException('Game hit check is invalid');
        }

        return $spec;
    }

    /**
     * Режим карточки подходит потоку.
     *
     * @param CheckSpec $spec Карточка.
     * @param string $flow Поток solo или joint.
     *
     * @return void
     *
     * @throws GameInvalidException Если режим чужой.
     */
    public function assertFlow(CheckSpec $spec, string $flow): void
    {
        $mode = $spec->getAllowedModes();
        if (!in_array($mode, ['solo', 'joint', 'both'], true)) {
            throw new GameInvalidException('Game check mode is invalid');
        }

        $fits = $flow === 'solo' ? $mode !== 'joint' : $mode !== 'solo';
        if (!$fits) {
            throw new GameInvalidException('Game check mode is invalid');
        }
    }

    /**
     * Характеристика и прикреплённые коды по предкам.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CheckSpec $start Карточка.
     * @param string $ruleCode Код.
     * @param bool $allowLater Разрешить шаг не этого хода.
     *
     * @return array{characteristic: string|null, attached: array<int, string>|null} Пара.
     *
     * @throws GameInvalidException Если предок не проверка.
     */
    public function chain(
        CharacterRuleSlice $slice,
        CheckSpec $start,
        string $ruleCode,
        bool $allowLater = false,
    ): array {
        $characteristic = null;
        $attached = null;
        $seen = [];
        $current = $start;
        $code = $ruleCode;
        while (!isset($seen[$code])) {
            $seen[$code] = true;
            $characteristic = $this->firstCharacteristic($characteristic, $current);
            $attached = $this->firstAttached($attached, $current);

            $parent = $current->getParentCheckCode();
            if ($parent === null || $parent === '') {
                break;
            }

            $current = $this->checkOf($slice, $parent, $allowLater);
            $code = $parent;
        }

        return ['characteristic' => $characteristic, 'attached' => $attached];
    }

    /**
     * Жетон, воля, неустойчивость и from_state на карточке попадания.
     *
     * @param CheckSpec $spec Карточка.
     *
     * @return bool Чужой признак.
     */
    private function hitBlocked(CheckSpec $spec): bool
    {
        if ($spec->isWillpower() || $spec->isUnstableCheck()) {
            return true;
        }

        return $spec->getDifficulty()->getKind() === 'from_state';
    }

    /**
     * Удар, каст, жетон, воля, неустойчивость и from_state — не этот шаг.
     *
     * @param CheckSpec $spec Карточка.
     *
     * @return bool Чужой шаг.
     */
    private function isLaterStep(CheckSpec $spec): bool
    {
        if ($spec->isHitCheck() || $spec->isUnstableCheck()) {
            return true;
        }

        return $spec->getDifficulty()->getKind() === 'from_state';
    }

    /**
     * Первая непустая характеристика цепочки.
     *
     * @param string|null $found Уже найденная или null.
     * @param CheckSpec $current Карточка.
     *
     * @return string|null Код.
     */
    private function firstCharacteristic(?string $found, CheckSpec $current): ?string
    {
        if ($found !== null) {
            return $found;
        }

        $code = $current->getCharacteristicCode();
        if ($code === null || $code === '') {
            return null;
        }

        return $code;
    }

    /**
     * Первый заданный список прикреплённых кодов. Пустой массив дальше не наследуется.
     *
     * @param array<int, string>|null $found Уже найденный или null.
     * @param CheckSpec $current Карточка.
     *
     * @return array<int, string>|null Список.
     */
    private function firstAttached(?array $found, CheckSpec $current): ?array
    {
        if ($found !== null) {
            return $found;
        }

        return $current->getAttachedRuleCodes();
    }
}
