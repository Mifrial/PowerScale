<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassComplexityTooHigh,MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong -- сборка binding, цепочка предка и бросок попадания одна причина.

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Dto\GameCheckThrowOverride;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Mechanic\Dto\RollAdjustment;
use Mifrial\Roleplay\Mechanic\Dto\RollMechanicPayload;
use Mifrial\Roleplay\Mechanic\Dto\RollResult;
use Mifrial\Roleplay\Mechanic\Dto\RollScoreAdjustPayload;
use Mifrial\Roleplay\Mechanic\Dto\RollSpec;
use Mifrial\Roleplay\Mechanic\Dto\SizedBase;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Mechanic\Service\DimensionalCheckNormalizer;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\State\CheckAdvantageEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Карточка проверки и вызов порта броска. Формулу кубов не пишет.
 */
final class GameCheckRoll
{
    private readonly DimensionalCheckNormalizer $normalizer;

    /**
     * Создаёт расчёт.
     *
     * @param ICharacterRuleSlices $slices Срез ревизии.
     * @param ICharacters $characters Персонажи.
     * @param IMechanics $mechanics Каталог.
     * @param IMechanicRolls $rolls Порт броска.
     * @param ICharacterSheets|null $sheets Пересчёт листа на чтении.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterRuleSlices $slices,
        private readonly ICharacters $characters,
        private readonly IMechanics $mechanics,
        private readonly IMechanicRolls $rolls,
        private readonly ?ICharacterSheets $sheets = null,
    ) {
        $this->normalizer = new DimensionalCheckNormalizer();
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

        $sheet = $record->getSheet();
        if ($this->sheets !== null) {
            $sheet = $this->sheets->compute(
                $this->slices->get($record->getSpaceId(), $record->getRulesRevision()),
                $record->getChoices(),
                $sheet,
            );
        }

        return ['version' => $record->getActualVersion(), 'sheet' => $sheet];
    }

    /**
     * Имя персонажа.
     *
     * @param int $characterId Персонаж.
     *
     * @return string Имя.
     *
     * @throws GameNotFoundException Если персонажа нет.
     */
    public function characterName(int $characterId): string
    {
        try {
            return $this->characters->get($characterId)->getName();
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game character was not found', $exception);
        }
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
     * @param GameCheckThrowOverride|null $override Смена пула или null.
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
        ?GameCheckThrowOverride $override = null,
    ): array {
        $slice = $this->slices->get($spaceId, $rulesRevision);

        return $this->thrown($slice, $this->checkOf($slice, $ruleCode), $ruleCode, $flow, $sheet, $asked, null, null, $override);
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
     * Бросает попадание с derived mastery и размерной эффективностью.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код проверки.
     * @param array<string, mixed> $sheet Лист участника.
     * @param DimensionalNumber $mastery Derived mastery.
     * @param DimensionalNumber|null $efficiency Эффективность профиля или live fallback.
     * @param array{base: int, size: int} $difficulty Фактическая трудность.
     * @param array<int, RollAdvantage> $advantages Явные преимущества действия.
     * @param RollAdjustment|null $adjustment Настройка граней и успехов карточки.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Итог.
     *
     * @throws GameInvalidException Если карточка или pool чужие.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function throwHitWithMastery(
        int $spaceId,
        int $rulesRevision,
        string $ruleCode,
        array $sheet,
        DimensionalNumber $mastery,
        ?DimensionalNumber $efficiency,
        array $difficulty,
        array $advantages = [],
        ?RollAdjustment $adjustment = null,
    ): array {
        $slice = $this->slices->get($spaceId, $rulesRevision);
        $check = $this->hitSpec($slice, $ruleCode);
        $chain = $this->chain($slice, $check, $ruleCode);
        $bindings = $this->bindings($slice);
        $payload = $this->rollPayload($bindings);
        $dieFaces = $payload->getDieFaces();
        if ($dieFaces === null) {
            throw new GameInvalidException('Game check roll rule is invalid');
        }

        $selectedEfficiency = $efficiency ?? $this->efficiency($check);
        $rolled = $this->rollMastery(
            $mastery,
            $selectedEfficiency,
            $efficiency !== null || $this->hasExplicitEfficiency($check),
            $dieFaces,
            $bindings,
            $chain['attached'],
            array_merge(
                $this->stateAdvantages($slice, $sheet, null, true),
                $advantages,
            ),
            $adjustment,
        );
        $minimum = ['base' => 0, 'size' => -1];
        if ($difficulty['base'] === 0 && $difficulty['size'] === 0) {
            $difficulty = $minimum;
        }

        $scored = $this->scored($difficulty, $rolled, new SizedBase(0, -1));
        $scored['poolSize'] = $mastery->getSize();
        $scored['efficiency'] = ['base' => $selectedEfficiency->getBase(), 'size' => $selectedEfficiency->getSize()];
        $scored['label'] = $slice->findLive($ruleCode)?->getName() ?? 'Попадание';
        $scored['raw'] = $scored['roll'];

        return $scored;
    }

    /**
     * Оценивает уже брошенный пул против новой трудности, не бросая кубы заново.
     *
     * @param array<string, mixed> $check Итог броска.
     * @param array{base: int, size: int} $difficulty Трудность.
     *
     * @return array<string, mixed> Тот же бросок с новым рейтингом.
     */
    public function rerate(array $check, array $difficulty): array
    {
        $roll = is_array($check['raw'] ?? null) ? $check['raw'] : $check['roll'];
        $rating = $this->rolls->rate(
            new SizedBase($roll['base'], $roll['size']),
            new SizedBase($difficulty['base'], $difficulty['size']),
            -1,
        );
        $check['difficulty'] = $difficulty;
        $check['success'] = $rating->isPassed();
        $check['rating'] = $rating->getRating();
        $check['min_success_size'] = -1;

        return $check;
    }

    /**
     * Бросок проверки с уже посчитанной трудностью. isLaterStep не применяется.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код проверки.
     * @param array<string, mixed> $sheet Лист.
     * @param array{base: int, size: int} $difficulty Трудность.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Итог.
     *
     * @throws GameInvalidException Если карточка или пул чужие.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function throwWithDifficulty(
        int $spaceId,
        int $rulesRevision,
        string $ruleCode,
        array $sheet,
        array $difficulty,
        ?GameCheckThrowOverride $override = null,
    ): array {
        $slice = $this->slices->get($spaceId, $rulesRevision);
        $check = $this->checkOf($slice, $ruleCode, true);

        return $this->thrown($slice, $check, $ruleCode, 'solo', $sheet, null, null, $difficulty, $override);
    }

    /**
     * Проверяет auto-fail боевого броска по размерному состоянию.
     *
     * @param array{base: int, size: int} $roll Размерный бросок.
     *
     * @return bool true, если бросок достиг боевого минимума.
     */
    public function isCombatAutoFail(array $roll): bool
    {
        return $this->normalizer->isMinimum(
            new SizedBase($roll['base'], $roll['size']),
            new SizedBase(0, -1),
        );
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
     * Живая карточка единственной проверки инициативы.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return CheckSpec Карточка.
     *
     * @throws GameInvalidException Если карточек нет или их несколько.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function initiativeCheck(int $spaceId, int $rulesRevision): CheckSpec
    {
        $slice = $this->slices->get($spaceId, $rulesRevision);

        return $this->checkOf($slice, $this->findInitiativeCode($spaceId, $rulesRevision));
    }

    /**
     * Число кубов и граней. Null не подменяется.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string|null $characteristic Код или null.
     * @param array<string, mixed> $sheet Лист.
     * @param RollMechanicPayload $payload Дефолты.
     *
     * @return array{diceCount: int, dieFaces: int} Пул.
     *
     * @throws GameInvalidException Если числа нет.
     */
    private function pool(
        CharacterRuleSlice $slice,
        ?string $characteristic,
        array $sheet,
        RollMechanicPayload $payload,
    ): array {
        $diceCount = $this->diceCount($slice, $characteristic, $sheet, $payload);
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
    private function scored(
        array $difficulty,
        RollResult $rolled,
        ?SizedBase $minimum = null,
    ): array {
        $roll = new SizedBase($rolled->getTotalSuccesses(), $rolled->getSpec()->getDieSize());
        if ($minimum !== null) {
            $roll = $this->normalizer->clamp($roll, $minimum);
        }

        $rating = $this->rolls->rate(
            $roll,
            new SizedBase($difficulty['base'], $difficulty['size']),
        );

        return [
            'difficulty' => $difficulty,
            'roll' => ['base' => $roll->getBase(), 'size' => $roll->getSize()],
            'success' => $rating->isPassed(),
            'rating' => $rating->getRating(),
            'faces' => $rolled->getRolls(),
            'adjustedRolls' => $rolled->getAdjustedRolls(),
            'successes' => $rolled->getSuccesses(),
            'droppedRolls' => $rolled->getDroppedRolls(),
            'dieFaces' => $rolled->getSpec()->getDieFaces(),
            'min_success_size' => $minimum?->getSize(),
        ];
    }

    /**
     * Выполняет бросок derived mastery.
     *
     * @param DimensionalNumber $mastery Мастерство.
     * @param DimensionalNumber $efficiency Эффективность.
     * @param bool $explicitEfficiency Признак явной эффективности.
     * @param int $dieFaces Число граней.
     * @param array<int, MechanicBinding> $bindings Срез механик.
     * @param array<int, string>|null $attached Прикреплённые правила.
     *
     * @return RollResult Результат броска.
     */
    private function rollMastery(
        DimensionalNumber $mastery,
        DimensionalNumber $efficiency,
        bool $explicitEfficiency,
        int $dieFaces,
        array $bindings,
        ?array $attached,
        array $advantages = [],
        ?RollAdjustment $adjustment = null,
    ): RollResult {
        if ($mastery->getBase() <= 0) {
            throw new GameInvalidException('Game check mastery pool is invalid');
        }

        return $this->rolls->roll(
            new RollSpec(
                max(1, $mastery->getBase()),
                $dieFaces,
                $efficiency->getBase(),
                $mastery->getSize() + $efficiency->getSize(),
                $advantages,
                $explicitEfficiency,
                $adjustment,
            ),
            static fn (): float => mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax(),
            $bindings,
            $this->mechanics->getList(),
            new ResolveActiveOptions(null, $attached),
        );
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
     * @param SizedBase|null $minimum Нижняя граница размерного roll.
     * @param array{base: int, size: int}|null $forced Принудительная трудность.
     * @param GameCheckThrowOverride|null $override Смена пула или null.
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
        ?SizedBase $minimum = null,
        ?array $forced = null,
        ?GameCheckThrowOverride $override = null,
    ): array {
        $this->assertFlow($check, $flow);
        $chain = $this->chain($slice, $check, $ruleCode, $forced !== null);
        $bindings = $this->bindings($slice);
        $pool = $this->poolOf($slice, $chain['characteristic'], $sheet, $this->rollPayload($bindings), $override);
        $difficulty = $forced ?? $this->difficulty($check, $asked);
        $efficiency = $this->efficiency($check);
        $rolled = $this->rolls->roll(
            new RollSpec(
                $pool['diceCount'],
                $pool['dieFaces'],
                $efficiency->getBase(),
                $efficiency->getSize(),
                $this->advantagesOf($override, $slice, $sheet, $chain['characteristic'], $check->isHitCheck()),
                $this->hasExplicitEfficiency($check),
            ),
            static fn (): float => mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax(),
            $bindings,
            $this->mechanics->getList(),
            new ResolveActiveOptions(null, $chain['attached']),
        );

        $scored = $this->scored($difficulty, $rolled, $minimum);
        $scored['label'] = $slice->findLive($ruleCode)?->getName() ?? '';

        return $scored;
    }

    /**
     * Пул карточки или смены.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string|null $characteristic Код цепочки.
     * @param array<string, mixed> $sheet Лист.
     * @param RollMechanicPayload $payload Дефолты.
     * @param GameCheckThrowOverride|null $override Смена или null.
     *
     * @return array{diceCount: int, dieFaces: int} Пул.
     *
     * @throws GameInvalidException Если числа нет.
     */
    private function poolOf(
        CharacterRuleSlice $slice,
        ?string $characteristic,
        array $sheet,
        RollMechanicPayload $payload,
        ?GameCheckThrowOverride $override,
    ): array {
        if ($override !== null && $override->diceCount !== null) {
            $dieFaces = $payload->getDieFaces();
            if ($dieFaces === null) {
                throw new GameInvalidException('Game check pool is invalid');
            }

            return ['diceCount' => $override->diceCount, 'dieFaces' => $dieFaces];
        }

        $code = $override?->characteristicCode ?? $characteristic;

        return $this->pool($slice, $code, $sheet, $payload);
    }

    /**
     * Преимущества свободного пула.
     *
     * @param GameCheckThrowOverride|null $override Смена или null.
     *
     * @return array<int, RollAdvantage> Записи.
     */
    private function advantagesOf(
        ?GameCheckThrowOverride $override,
        ?CharacterRuleSlice $slice = null,
        ?array $sheet = null,
        ?string $characteristicCode = null,
        bool $hit = false,
    ): array {
        $rows = [];
        if ($override !== null && $override->advantage !== null && $override->advantage !== 0) {
            $rows[] = new RollAdvantage('roll', $override->advantage);
        }

        if ($slice !== null && is_array($sheet)) {
            array_push($rows, ...$this->stateAdvantages($slice, $sheet, $characteristicCode, $hit));
        }

        return $rows;
    }

    /**
     * Помехи состояний на попадание или характеристику и её производные.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param string|null $characteristicCode Характеристика проверки или null.
     * @param bool $hit Попадание.
     *
     * @return array<int, RollAdvantage> Записи.
     */
    private function stateAdvantages(
        CharacterRuleSlice $slice,
        array $sheet,
        ?string $characteristicCode,
        bool $hit,
    ): array {
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            return [];
        }

        $rows = [];
        foreach ($states as $state) {
            if (!is_array($state)) {
                continue;
            }

            $code = $state['stateRuleCode'] ?? null;
            $spec = is_string($code) ? $slice->findLive($code)?->getSpec() : null;
            if (!$spec instanceof StateSpec) {
                continue;
            }

            $units = is_int($state['value'] ?? null) ? $state['value'] : 1;
            foreach ($spec->getEffects() as $effect) {
                if (!$effect instanceof CheckAdvantageEffect || !$this->advantageMatches($slice, $effect, $characteristicCode, $hit)) {
                    continue;
                }

                $delta = $effect->getAmount() * ($effect->isPerUnit() ? $units : 1);
                $max = $effect->getMaxAbs();
                if ($max !== null) {
                    $delta = max(-$max, min($max, $delta));
                }

                if ($delta !== 0) {
                    $rows[] = new RollAdvantage(is_string($code) ? $code : 'state', $delta);
                }
            }
        }

        return $rows;
    }

    /**
     * Эффект относится к этому броску.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CheckAdvantageEffect $effect Эффект.
     * @param string|null $characteristicCode Характеристика.
     * @param bool $hit Попадание.
     *
     * @return bool true, если помеха входит в бросок.
     */
    private function advantageMatches(
        CharacterRuleSlice $slice,
        CheckAdvantageEffect $effect,
        ?string $characteristicCode,
        bool $hit,
    ): bool {
        if ($hit && $effect->isIncludesHit()) {
            return true;
        }

        if ($characteristicCode === null) {
            return false;
        }

        foreach ($effect->getCharacteristicCodes() as $root) {
            if ($this->dependsOn($slice, $characteristicCode, $root)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Код сам или формула min/max от корня.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Проверяемая характеристика.
     * @param string $root Корень эффекта.
     *
     * @return bool true, если зависит.
     */
    private function dependsOn(CharacterRuleSlice $slice, string $code, string $root, array $seen = []): bool
    {
        if ($code === $root || isset($seen[$code])) {
            return $code === $root;
        }

        $seen[$code] = true;
        $spec = $slice->findLive($code)?->getSpec();
        $formula = $spec instanceof CharacteristicSpec ? $spec->getFormula() : null;
        if (!is_string($formula) || preg_match('/^(?:min|max)\(\s*([^,]+)\s*,\s*([^)]+)\)$/i', trim($formula), $match) !== 1) {
            return false;
        }

        return $this->dependsOn($slice, trim($match[1]), $root, $seen)
            || $this->dependsOn($slice, trim($match[2]), $root, $seen);
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
        $blocked = $spec instanceof CheckSpec ? $this->hitBlocked($spec) : true;
        if (!$spec instanceof CheckSpec || !$spec->isHitCheck() || $blocked) {
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
        if ($spec->isWillpower() || $spec->isUnstableCheck()) {
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
    private function checkOf(CharacterRuleSlice $slice, string $ruleCode, bool $allowLater = false): CheckSpec
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
    private function chain(CharacterRuleSlice $slice, CheckSpec $start, string $ruleCode, bool $allowLater = false): array
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

            $current = $this->checkOf($slice, $parent, $allowLater);
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
    private function diceCount(
        CharacterRuleSlice $slice,
        ?string $characteristic,
        array $sheet,
        RollMechanicPayload $payload,
    ): ?int {
        if ($characteristic === null) {
            return $payload->getDiceCount();
        }

        return $this->resolvedBase($slice, $sheet, $characteristic, []);
    }

    /**
     * Закупка или производная min/max.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Код.
     * @param array<string, true> $seen Уже разобранные коды.
     *
     * @return int База пула.
     *
     * @throws GameInvalidException Если значения нет.
     */
    private function resolvedBase(CharacterRuleSlice $slice, array $sheet, string $code, array $seen): int
    {
        $bought = $this->purchasedBase($sheet, $code);
        if ($bought !== null) {
            return $bought;
        }

        return $this->derivedBase($slice, $sheet, $code, $seen);
    }

    /**
     * База закупки или null, если кода нет.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Код.
     *
     * @return int|null База или null.
     *
     * @throws GameInvalidException Если строка есть, а value чужой.
     */
    private function purchasedBase(array $sheet, string $code): ?int
    {
        $rows = $sheet['characteristicPurchases'] ?? [];
        if (!is_array($rows)) {
            throw new GameInvalidException('Game check characteristic is invalid');
        }

        foreach ($rows as $row) {
            if (!is_array($row) || ($row['characteristicCode'] ?? null) !== $code) {
                continue;
            }

            $value = $row['value'] ?? null;
            $base = is_array($value) ? ($value['base'] ?? null) : null;
            if (!is_int($base)) {
                throw new GameInvalidException('Game check characteristic is invalid');
            }

            return $base;
        }

        return null;
    }

    /**
     * База по formula или automatic.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Код.
     * @param array<string, true> $seen Уже разобранные коды.
     *
     * @return int База.
     *
     * @throws GameInvalidException Если формулы нет или операнды чужие.
     */
    private function derivedBase(CharacterRuleSlice $slice, array $sheet, string $code, array $seen): int
    {
        if (isset($seen[$code])) {
            throw new GameInvalidException('Game check characteristic is invalid');
        }

        $seen[$code] = true;
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof CharacteristicSpec) {
            throw new GameInvalidException('Game check characteristic is invalid');
        }

        $parsed = $this->parsedDerived($spec->getFormula());
        if ($parsed !== null) {
            $left = $this->resolvedBase($slice, $sheet, $parsed['left'], $seen);
            $right = $this->resolvedBase($slice, $sheet, $parsed['right'], $seen);
            if ($parsed['operator'] === 'min') {
                return min($left, $right);
            }

            return max($left, $right);
        }

        $automatic = $this->automaticBase($spec);
        if ($automatic !== null) {
            return $automatic;
        }

        throw new GameInvalidException('Game check characteristic is invalid');
    }

    /**
     * База automatic-спеки без закупки.
     *
     * @param CharacteristicSpec $spec Карточка.
     *
     * @return int|null База или null.
     */
    private function automaticBase(CharacteristicSpec $spec): ?int
    {
        $automatic = $spec->getAutomatic();
        if ($automatic === true) {
            return CharacteristicNumber::BASE_MIN;
        }

        if ($automatic instanceof CharacteristicNumber) {
            return $automatic->getBase();
        }

        return null;
    }

    /**
     * Формула min(a, b) или max(a, b).
     *
     * @param string|null $formula Спека.
     *
     * @return array{operator: string, left: string, right: string}|null Разбор или null.
     */
    private function parsedDerived(?string $formula): ?array
    {
        if ($formula === null || $formula === '') {
            return null;
        }

        if (preg_match('/^(min|max)\(\s*([^,]+)\s*,\s*([^)]+)\)$/i', trim($formula), $match) !== 1) {
            return null;
        }

        return [
            'operator' => strtolower($match[1]),
            'left' => trim($match[2]),
            'right' => trim($match[3]),
        ];
    }

    /**
     * Возвращает live efficiency проверки или neutral fallback.
     *
     * @param CheckSpec $check Карточка проверки.
     *
     * @return DimensionalNumber Размерная эффективность.
     */
    private function efficiency(CheckSpec $check): DimensionalNumber
    {
        $value = $check->getDefaultEfficiency();

        return new DimensionalNumber($value ?? 3, 0);
    }

    /**
     * Проверяет наличие efficiency в live CheckSpec.
     *
     * @param CheckSpec $check Карточка проверки.
     *
     * @return bool true, если значение задано явно.
     */
    private function hasExplicitEfficiency(CheckSpec $check): bool
    {
        return $check->getDefaultEfficiency() !== null;
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
