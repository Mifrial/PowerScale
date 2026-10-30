<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ApplyStateEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation\PostureOperation;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Операции листа из формы действия: apply_state, posture и снятие устойчивости.
 *
 * Логика читает только спеку карточки и флаги состояний, коды правил не знает.
 */
final class GameActionStates
{
    /**
     * Операции листа для действия.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист актора.
     * @param AbilityActionSpec $spec Спека действия.
     *
     * @return list<array<string, mixed>> Операции листа.
     *
     * @throws GameInvalidException Если состояние или операция битые.
     */
    public function operations(CharacterRuleSlice $slice, array $sheet, AbilityActionSpec $spec): array
    {
        $operations = [];
        foreach ($spec->getBase()->getActionEffects() as $effect) {
            if ($effect instanceof ApplyStateEffect) {
                $operations[] = $this->putState($slice, $effect);
            }
        }

        foreach ($spec->getOperations() as $operation) {
            if (!$operation instanceof PostureOperation) {
                throw new GameInvalidException('Game action operation is not supported on the server');
            }

            $operations = [...$operations, ...$this->posture($slice, $sheet, $operation)];
        }

        if ($spec->getBase()->getCombatAction() === 'recover-stability') {
            $operations = [...$operations, ...$this->removals($slice, $sheet)];
        }

        return $operations;
    }

    /**
     * putState по эффекту apply_state.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param ApplyStateEffect $effect Эффект.
     *
     * @return array<string, mixed> Операция.
     *
     * @throws GameInvalidException Если карточки состояния нет.
     */
    private function putState(CharacterRuleSlice $slice, ApplyStateEffect $effect): array
    {
        $code = $effect->getStateCode();
        $this->stateSpec($slice, $code);
        $operation = ['kind' => 'putState', 'stateRuleCode' => $code];
        $amount = $effect->getAmount();
        if ($amount instanceof DimensionalNumber) {
            $operation['value'] = ['base' => $amount->getBase(), 'size' => $amount->getSize()];
        } elseif (is_int($amount)) {
            $operation['value'] = $amount;
        }

        return $operation;
    }

    /**
     * Переключение лежачего положения.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист актора.
     * @param PostureOperation $operation Операция.
     *
     * @return list<array<string, mixed>> Операции.
     *
     * @throws GameInvalidException Если поза или состояние битые.
     */
    private function posture(CharacterRuleSlice $slice, array $sheet, PostureOperation $operation): array
    {
        if ($operation->getPosture() !== 'standing_or_prone') {
            throw new GameInvalidException('Game action posture is invalid');
        }

        $code = $this->singleFlag($slice, 'lying');
        $id = $this->rowId($sheet, $code);
        if ($id === null) {
            return [['kind' => 'putState', 'stateRuleCode' => $code]];
        }

        return [['kind' => 'removeState', 'id' => $id]];
    }

    /**
     * Снятие строк состояний с флагом.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист актора.
     *
     * @return list<array<string, mixed>> Операции.
     *
     * @throws GameInvalidException Если состояние битое.
     */
    private function removals(CharacterRuleSlice $slice, array $sheet): array
    {
        $code = $this->flagCode($slice, 'unstable');
        if ($code === null) {
            return [];
        }

        $operations = [];
        foreach ($this->rows($sheet) as $row) {
            if (!is_array($row) || ($row['stateRuleCode'] ?? null) !== $code) {
                continue;
            }

            $id = $row['id'] ?? null;
            if (!is_int($id)) {
                throw new GameInvalidException('Game action state row is invalid');
            }

            $operations[] = ['kind' => 'removeState', 'id' => $id];
        }

        return $operations;
    }

    /**
     * Единственное живое состояние с флагом.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param string $flag Флаг спеки: lying или unstable.
     *
     * @return string Код состояния.
     *
     * @throws GameInvalidException Если состояние не одно или его нет.
     */
    private function singleFlag(CharacterRuleSlice $slice, string $flag): string
    {
        $code = $this->flagCode($slice, $flag);
        if ($code === null) {
            throw new GameInvalidException('Game action state flag is invalid');
        }

        return $code;
    }

    /**
     * Код состояния с флагом или null.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param string $flag Флаг спеки: lying или unstable.
     *
     * @return string|null Код состояния.
     *
     * @throws GameInvalidException Если состояний с флагом больше одного.
     */
    private function flagCode(CharacterRuleSlice $slice, string $flag): ?string
    {
        $codes = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if (!$spec instanceof StateSpec) {
                continue;
            }

            if (($flag === 'lying' && $spec->isLying()) || ($flag === 'unstable' && $spec->isUnstable())) {
                $codes[] = $rule->getCode();
            }
        }

        if (count($codes) > 1) {
            throw new GameInvalidException('Game action state flag is invalid');
        }

        return $codes[0] ?? null;
    }

    /**
     * Живая карточка состояния.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param string $code Код.
     *
     * @return StateSpec Спека.
     *
     * @throws GameInvalidException Если карточки нет или это не state.
     */
    private function stateSpec(CharacterRuleSlice $slice, string $code): StateSpec
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof StateSpec) {
            throw new GameInvalidException('Game action state is invalid');
        }

        return $spec;
    }

    /**
     * Id строки состояния.
     *
     * @param array<string, mixed> $sheet Лист актора.
     * @param string $code Код.
     *
     * @return int|null Id или null.
     *
     * @throws GameInvalidException Если строка без id.
     */
    private function rowId(array $sheet, string $code): ?int
    {
        foreach ($this->rows($sheet) as $row) {
            if (!is_array($row) || ($row['stateRuleCode'] ?? null) !== $code) {
                continue;
            }

            $id = $row['id'] ?? null;
            if (!is_int($id)) {
                throw new GameInvalidException('Game action state row is invalid');
            }

            return $id;
        }

        return null;
    }

    /**
     * Строки состояний листа.
     *
     * @param array<string, mixed> $sheet Лист актора.
     *
     * @return array<int, mixed> Строки.
     */
    private function rows(array $sheet): array
    {
        $states = $sheet['states'] ?? [];

        return is_array($states) ? $states : [];
    }
}
