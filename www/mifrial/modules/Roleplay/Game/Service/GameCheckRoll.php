<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassComplexityTooHigh,MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong -- сборка binding, цепочка предка и бросок попадания одна причина.

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Dto\RollMechanicPayload;
use Mifrial\Roleplay\Mechanic\Dto\RollResult;
use Mifrial\Roleplay\Mechanic\Dto\RollScoreAdjustPayload;
use Mifrial\Roleplay\Mechanic\Dto\RollSpec;
use Mifrial\Roleplay\Mechanic\Dto\SizedBase;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;

/**
 * Карточка проверки и вызов порта броска. Формулу кубов не пишет.
 */
final class GameCheckRoll
{
    /**
     * Создаёт расчёт.
     *
     * @param ICharacterRuleSlices $slices Срез ревизии.
     * @param ICharacters $characters Персонажи.
     * @param IMechanics $mechanics Каталог.
     * @param IMechanicRolls $rolls Порт броска.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterRuleSlices $slices,
        private readonly ICharacters $characters,
        private readonly IMechanics $mechanics,
        private readonly IMechanicRolls $rolls,
    ) {
    }

    /**
     * Лист персонажа.
     *
     * @param int $characterId Персонаж.
     *
     * @return array{version: int, sheet: array<string, mixed>} Пара.
     *
     * @throws GameNotFoundException Если строки нет.
     */
    public function characterSheet(int $characterId): array
    {
        try {
            $record = $this->characters->get($characterId);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game character was not found', $exception);
        }

        return ['version' => $record->getActualVersion(), 'sheet' => $record->getSheet()];
    }

    /**
     * Список операций листа. В этом заходе пуст.
     *
     * @return array<int, array<string, mixed>> Каталог порта.
     */
    public function operations(): array
    {
        return [];
    }

    /**
     * Проверяет карточку, не бросая кубы.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код.
     * @param string $flow Поток solo или joint.
     * @param array{base: int, size: int}|null $asked Трудность ask.
     *
     * @return void
     *
     * @throws GameInvalidException Если карточка чужая.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function assertRule(int $spaceId, int $rulesRevision, string $ruleCode, string $flow, ?array $asked): void
    {
        $slice = $this->slices->get($spaceId, $rulesRevision);
        $check = $this->checkOf($slice, $ruleCode);
        $this->assertFlow($check, $flow);
        if ($check->getDifficulty()->getKind() === 'ask' && $asked === null) {
            throw new GameInvalidException('Game check difficulty is invalid');
        }

        if ($check->getDifficulty()->getKind() !== 'ask' && $check->getDifficulty()->getKind() !== 'none') {
            throw new GameInvalidException('Game check difficulty is invalid');
        }
    }

    /**
     * Считает трудность, бросок и успех.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код проверки.
     * @param string $flow Поток solo или joint.
     * @param array<string, mixed> $sheet Лист участника.
     * @param array{base: int, size: int}|null $asked Трудность ask или null.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Итог.
     *
     * @throws GameInvalidException Если карточка или пул чужие.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function throwCheck(
        int $spaceId,
        int $rulesRevision,
        string $ruleCode,
        string $flow,
        array $sheet,
        ?array $asked,
    ): array {
        $slice = $this->slices->get($spaceId, $rulesRevision);

        return $this->thrown($slice, $this->checkOf($slice, $ruleCode), $ruleCode, $flow, $sheet, $asked);
    }

    /**
     * Код единственной живой проверки попадания.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return string Код карточки.
     *
     * @throws GameInvalidException Если карточек нет или их несколько.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function findHitCode(int $spaceId, int $rulesRevision): string
    {
        $matched = [];
        foreach ($this->slices->get($spaceId, $rulesRevision)->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof CheckSpec && $spec->isHitCheck()) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) !== 1) {
            throw new GameInvalidException('Game hit check is invalid');
        }

        return $matched[0];
    }

    /**
     * Бросает карточку попадания. isHitCheck в checkOf не снимает.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код уже найденной карточки.
     * @param array<string, mixed> $sheet Лист атакующего.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Итог.
     *
     * @throws GameInvalidException Если карточка или пул чужие.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function throwHit(int $spaceId, int $rulesRevision, string $ruleCode, array $sheet): array
    {
        $slice = $this->slices->get($spaceId, $rulesRevision);

        return $this->thrown($slice, $this->hitSpec($slice, $ruleCode), $ruleCode, 'solo', $sheet, null);
    }

    /**
     * Код единственной живой проверки с признаком инициативы.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return string Код карточки.
     *
     * @throws GameInvalidException Если карточек нет или их несколько.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function findInitiativeCode(int $spaceId, int $rulesRevision): string
    {
        $matched = [];
        foreach ($this->slices->get($spaceId, $rulesRevision)->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof CheckSpec && $spec->isInitiative()) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) !== 1) {
            throw new GameInvalidException('Game initiative check is invalid');
        }

        return $matched[0];
    }

    /**
     * Число кубов и граней. Null не подменяется.
     *
     * @param string|null $characteristic Код или null.
     * @param array<string, mixed> $sheet Лист.
     * @param RollMechanicPayload $payload Дефолты.
     *
     * @return array{diceCount: int, dieFaces: int} Пул.
     *
     * @throws GameInvalidException Если числа нет.
     */
    private function pool(?string $characteristic, array $sheet, RollMechanicPayload $payload): array
    {
        $diceCount = $this->diceCount($characteristic, $sheet, $payload);
        $dieFaces = $payload->getDieFaces();
        if ($diceCount === null || $dieFaces === null) {
            throw new GameInvalidException('Game check pool is invalid');
        }

        return ['diceCount' => $diceCount, 'dieFaces' => $dieFaces];
    }

    /**
     * Трудность ask или нулевая.
     *
     * @param CheckSpec $check Карточка.
     * @param array{base: int, size: int}|null $asked Решение.
     *
     * @return array{base: int, size: int} Пара.
     *
     * @throws GameInvalidException Если ask без решения.
     */
    private function difficulty(CheckSpec $check, ?array $asked): array
    {
        if ($check->getDifficulty()->getKind() !== 'ask') {
            return ['base' => 0, 'size' => 0];
        }

        if ($asked === null) {
            throw new GameInvalidException('Game check difficulty is invalid');
        }

        return $asked;
    }

    /**
     * Сравнивает бросок с трудностью.
     *
     * @param array{base: int, size: int} $difficulty Трудность.
     * @param RollResult $rolled Бросок.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Итог.
     */
    private function scored(array $difficulty, RollResult $rolled): array
    {
        $rating = $this->rolls->rate(
            new SizedBase($rolled->getTotalSuccesses(), $rolled->getSpec()->getDieSize()),
            new SizedBase($difficulty['base'], $difficulty['size']),
        );

        return [
            'difficulty' => $difficulty,
            'roll' => ['base' => $rolled->getTotalSuccesses(), 'size' => $rolled->getSpec()->getDieSize()],
            'success' => $rating->isPassed(),
            'rating' => $rating->getRating(),
        ];
    }

    /**
     * Тот же roll и rate, что у throwCheck, после отбора карточки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CheckSpec $check Карточка.
     * @param string $ruleCode Код.
     * @param string $flow Поток solo или joint.
     * @param array<string, mixed> $sheet Лист.
     * @param array{base: int, size: int}|null $asked Трудность ask или null.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Итог.
     *
     * @throws GameInvalidException Если режим или пул чужие.
     */
    private function thrown(
        CharacterRuleSlice $slice,
        CheckSpec $check,
        string $ruleCode,
        string $flow,
        array $sheet,
        ?array $asked,
    ): array {
        $this->assertFlow($check, $flow);
        $chain = $this->chain($slice, $check, $ruleCode);
        $bindings = $this->bindings($slice);
        $pool = $this->pool($chain['characteristic'], $sheet, $this->rollPayload($bindings));
        $difficulty = $this->difficulty($check, $asked);
        $rolled = $this->rolls->roll(
            new RollSpec($pool['diceCount'], $pool['dieFaces'], 3, 0, []),
            static fn (): float => mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax(),
            $bindings,
            $this->mechanics->getList(),
            new ResolveActiveOptions(null, $chain['attached']),
        );

        return $this->scored($difficulty, $rolled);
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
    private function hitSpec(CharacterRuleSlice $slice, string $ruleCode): CheckSpec
    {
        $rule = $slice->findLive($ruleCode);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof CheckSpec || !$spec->isHitCheck() || $this->hitBlocked($spec)) {
            throw new GameInvalidException('Game hit check is invalid');
        }

        return $spec;
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
        if ($spec->isConcentrationToken() || $spec->isWillpower() || $spec->isUnstableCheck()) {
            return true;
        }

        return $spec->getDifficulty()->getKind() === 'from_state';
    }

    /**
     * Живая карточка проверки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $ruleCode Код.
     *
     * @return CheckSpec Карточка.
     *
     * @throws GameInvalidException Если это не проверка этого шага.
     */
    private function checkOf(CharacterRuleSlice $slice, string $ruleCode): CheckSpec
    {
        $rule = $slice->findLive($ruleCode);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof CheckSpec) {
            throw new GameInvalidException('Game check rule is invalid');
        }

        if ($this->isLaterStep($spec)) {
            throw new GameInvalidException('Game check rule is invalid');
        }

        return $spec;
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
        if ($spec->isHitCheck() || $spec->isConcentrationToken()) {
            return true;
        }

        if ($spec->isWillpower() || $spec->isUnstableCheck()) {
            return true;
        }

        return $spec->getDifficulty()->getKind() === 'from_state';
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
    private function assertFlow(CheckSpec $spec, string $flow): void
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
     *
     * @return array{characteristic: string|null, attached: array<int, string>|null} Пара.
     *
     * @throws GameInvalidException Если предок не проверка.
     */
    private function chain(CharacterRuleSlice $slice, CheckSpec $start, string $ruleCode): array
    {
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

            $current = $this->checkOf($slice, $parent);
            $code = $parent;
        }

        return ['characteristic' => $characteristic, 'attached' => $attached];
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

    /**
     * Число кубов.
     *
     * @param string|null $characteristic Код или null.
     * @param array<string, mixed> $sheet Лист.
     * @param RollMechanicPayload $payload Дефолты броска.
     *
     * @return int|null Число или null.
     *
     * @throws GameInvalidException Если закупки нет.
     */
    private function diceCount(?string $characteristic, array $sheet, RollMechanicPayload $payload): ?int
    {
        if ($characteristic === null) {
            return $payload->getDiceCount();
        }

        $rows = $sheet['characteristicPurchases'] ?? [];
        if (!is_array($rows)) {
            throw new GameInvalidException('Game check characteristic is invalid');
        }

        foreach ($rows as $row) {
            if (!is_array($row) || ($row['characteristicCode'] ?? null) !== $characteristic) {
                continue;
            }

            $value = $row['value'] ?? null;
            $base = is_array($value) ? ($value['base'] ?? null) : null;
            if (!is_int($base)) {
                throw new GameInvalidException('Game check characteristic is invalid');
            }

            return $base;
        }

        throw new GameInvalidException('Game check characteristic is invalid');
    }

    /**
     * Первый payload броска.
     *
     * @param array<int, MechanicBinding> $bindings Срезы.
     *
     * @return RollMechanicPayload Дефолты.
     *
     * @throws GameInvalidException Если правила броска нет.
     */
    private function rollPayload(array $bindings): RollMechanicPayload
    {
        foreach ($bindings as $binding) {
            $payload = $binding->getMechanicPayload();
            if ($payload instanceof RollMechanicPayload) {
                return $payload;
            }
        }

        throw new GameInvalidException('Game check roll rule is invalid');
    }

    /**
     * Binding всех механик среза.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array<int, MechanicBinding> Срезы.
     */
    private function bindings(CharacterRuleSlice $slice): array
    {
        $bindings = [];
        foreach ($slice->getLiveRules() as $rule) {
            foreach ($rule->getMechanics() as $row) {
                $binding = $this->bindingOf($rule, $row);
                if ($binding !== null) {
                    $bindings[] = $binding;
                }
            }
        }

        return $bindings;
    }

    /**
     * Одна строка механики.
     *
     * @param CharacterResolvedRule $rule Правило.
     * @param mixed $row Строка.
     *
     * @return MechanicBinding|null Binding или null.
     */
    private function bindingOf(CharacterResolvedRule $rule, mixed $row): ?MechanicBinding
    {
        if (!is_array($row) || !is_int($row['mechanic_id'] ?? null)) {
            return null;
        }

        $payload = is_array($row['mechanic_payload'] ?? null) ? $row['mechanic_payload'] : [];

        return new MechanicBinding($rule->getCode(), $row['mechanic_id'], $this->payloadOf($payload));
    }

    /**
     * Payload известного типа или null.
     *
     * @param array<string, mixed> $payload JSON.
     *
     * @return RollMechanicPayload|RollScoreAdjustPayload|null DTO или null.
     */
    private function payloadOf(array $payload): RollMechanicPayload|RollScoreAdjustPayload|null
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        if (($payload['type'] ?? null) === 'roll') {
            return new RollMechanicPayload(
                $this->optionalInt($data, 'diceCount'),
                $this->optionalInt($data, 'dieFaces'),
                $this->optionalInt($data, 'efficiency'),
                $this->optionalInt($data, 'adv'),
                $this->optionalInt($data, 'dieSize'),
                $this->stringList($data['sub_mechanics'] ?? null),
            );
        }

        if (($payload['type'] ?? null) === 'roll_score_adjust') {
            return new RollScoreAdjustPayload(
                $this->optionalInt($data, 'oneDelta'),
                $this->optionalInt($data, 'faceDelta'),
            );
        }

        return null;
    }

    /**
     * Целое поле или null.
     *
     * @param array<string, mixed> $data Объект.
     * @param string $field Имя.
     *
     * @return int|null Число или null.
     */
    private function optionalInt(array $data, string $field): ?int
    {
        $value = $data[$field] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * Список строк или null.
     *
     * @param mixed $value Значение.
     *
     * @return array<int, string>|null Список или null.
     */
    private function stringList(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $codes = [];
        foreach ($value as $code) {
            if (is_string($code)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
