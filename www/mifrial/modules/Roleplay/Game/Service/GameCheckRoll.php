<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassComplexityTooHigh,MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong -- сборка binding, цепочка предка и бросок попадания одна причина.

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Dto\GameCheckThrowOverride;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Dto\RollAdjustment;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
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
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Карточка проверки и вызов порта броска. Формулу кубов не пишет.
 */
final class GameCheckRoll
{
    public readonly GameCheckSpecs $specs;

    private readonly GameCheckChain $chains;

    private readonly DimensionalCheckNormalizer $normalizer;

    /**
     * Создаёт расчёт.
     *
     * @param ICharacterRuleSlices $slices Срез ревизии.
     * @param ICharacters $characters Персонажи.
     * @param IMechanics $mechanics Каталог.
     * @param IMechanicRolls $rolls Порт броска.
     * @param ICharacterFormulaContexts $contexts Живые характеристики листа.
     * @param ICharacterSheets|null $sheets Пересчёт листа на чтении.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterRuleSlices $slices,
        private readonly ICharacters $characters,
        private readonly IMechanics $mechanics,
        private readonly IMechanicRolls $rolls,
        private readonly ICharacterFormulaContexts $contexts,
        private readonly ?ICharacterSheets $sheets = null,
    ) {
        $this->chains = new GameCheckChain();
        $this->specs = new GameCheckSpecs($slices, $characters, $sheets, $this->chains);
        $this->normalizer = new DimensionalCheckNormalizer();
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
     * @throws CharacterNotFoundException Если ревизии нет.
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

        return $this->thrown($slice, $this->chains->checkOf($slice, $ruleCode), $ruleCode, $flow, $sheet, $asked, null, null, $override);
    }

    /**
     * Двусторонняя проверка: у каждой стороны свой пул, сложность — успехи другой.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код проверки.
     * @param array<string, mixed> $initiatorSheet Лист инициатора.
     * @param GameCheckThrowOverride|null $initiatorOverride Смена инициатора.
     * @param array<string, mixed> $opponentSheet Лист цели.
     * @param GameCheckThrowOverride|null $opponentOverride Смена цели.
     *
     * @return array{initiator: array<string, mixed>, opponent: array<string, mixed>} Итоги.
     *
     * @throws GameInvalidException Если карточка, режим или пул чужие.
     * @throws CharacterNotFoundException Если ревизии нет.
     */
    public function throwJointCheck(
        int $spaceId,
        int $rulesRevision,
        string $ruleCode,
        array $initiatorSheet,
        ?GameCheckThrowOverride $initiatorOverride,
        array $opponentSheet,
        ?GameCheckThrowOverride $opponentOverride,
    ): array {
        $context = $this->jointContext($spaceId, $rulesRevision, $ruleCode);
        $initiator = $this->rolledSideOf($context, $initiatorSheet, $initiatorOverride);
        $opponent = $this->rolledSideOf($context, $opponentSheet, $opponentOverride);
        $label = $context['slice']->findLive($ruleCode)?->getName() ?? '';

        return $this->jointResult($initiator, $opponent, $label, $label);
    }

    /**
     * Совместная проверка с разными карточками у сторон.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $initiatorRuleCode Проверка инициатора.
     * @param array<string, mixed> $initiatorSheet Лист инициатора.
     * @param GameCheckThrowOverride|null $initiatorOverride Смена инициатора.
     * @param string $opponentRuleCode Проверка противника.
     * @param array<string, mixed> $opponentSheet Лист противника.
     * @param GameCheckThrowOverride|null $opponentOverride Смена противника.
     *
     * @return array{initiator: array<string, mixed>, opponent: array<string, mixed>} Броски.
     *
     * @throws GameInvalidException Если карточка или пул чужие.
     */
    public function throwJointCheckSides(
        int $spaceId,
        int $rulesRevision,
        string $initiatorRuleCode,
        array $initiatorSheet,
        ?GameCheckThrowOverride $initiatorOverride,
        string $opponentRuleCode,
        array $opponentSheet,
        ?GameCheckThrowOverride $opponentOverride,
    ): array {
        $contexts = $this->sideContexts($spaceId, $rulesRevision, $initiatorRuleCode, $opponentRuleCode);
        $initiator = $this->rolledSideOf($contexts['initiator'], $initiatorSheet, $initiatorOverride);
        $opponent = $this->rolledSideOf($contexts['opponent'], $opponentSheet, $opponentOverride);
        $initiatorLabel = $contexts['initiator']['slice']->findLive($initiatorRuleCode)?->getName() ?? '';
        $opponentLabel = $contexts['opponent']['slice']->findLive($opponentRuleCode)?->getName() ?? '';

        return $this->jointResult($initiator, $opponent, $initiatorLabel, $opponentLabel);
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
     * @throws CharacterNotFoundException Если ревизии нет.
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
        $check = $this->chains->hitSpec($slice, $ruleCode);
        $chain = $this->chains->chain($slice, $check, $ruleCode);
        $bindings = $this->bindings($slice);
        $dieFaces = $this->dieFaces($this->rollPayload($bindings));
        $selected = $efficiency ?? $this->efficiency($check);
        $rolled = $this->rollMastery(
            $mastery,
            $selected,
            $efficiency !== null || $this->hasExplicitEfficiency($check),
            $dieFaces,
            $bindings,
            $chain['attached'],
            $this->hitAdvantages($slice, $sheet, $advantages),
            $adjustment,
        );

        return $this->hitResult($slice, $ruleCode, $difficulty, $mastery, $selected, $rolled);
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
     * @param GameCheckThrowOverride|null $override Смена пула или null.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Итог.
     *
     * @throws GameInvalidException Если карточка или пул чужие.
     * @throws CharacterNotFoundException Если ревизии нет.
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
        $check = $this->chains->checkOf($slice, $ruleCode, true);

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
     * Бросок одной стороны совместной проверки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CheckSpec $check Карточка.
     * @param array{characteristic: string|null, attached: array<int, string>} $chain Цепочка.
     * @param array<int, MechanicBinding> $bindings Механики.
     * @param RollMechanicPayload $payload Пул по умолчанию.
     * @param DimensionalNumber $efficiency Эффективность.
     * @param array<string, mixed> $sheet Лист.
     * @param GameCheckThrowOverride|null $override Смена пула.
     *
     * @return RollResult Бросок.
     *
     * @throws GameInvalidException Если пул чужой.
     */
    private function rolledSide(
        CharacterRuleSlice $slice,
        CheckSpec $check,
        array $chain,
        array $bindings,
        RollMechanicPayload $payload,
        DimensionalNumber $efficiency,
        array $sheet,
        ?GameCheckThrowOverride $override,
    ): RollResult {
        $pool = $this->poolOf($chain['characteristic'], $sheet, $payload, $override);

        return $this->rollPool(
            $pool,
            $slice,
            $sheet,
            $chain['characteristic'],
            $check,
            $override,
            $efficiency,
            $bindings,
            $chain,
        );
    }


    /**
     * Контекст совместной проверки одной карточки.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код проверки.
     *
     * @return array{slice: CharacterRuleSlice, check: CheckSpec, chain: array<string, mixed>, bindings: array<int, MechanicBinding>, payload: RollMechanicPayload, efficiency: DimensionalNumber} Контекст.
     *
     * @throws GameInvalidException Если карточка, режим или пул чужие.
     * @throws CharacterNotFoundException Если ревизии нет.
     */
    private function jointContext(int $spaceId, int $rulesRevision, string $ruleCode): array
    {
        $slice = $this->slices->get($spaceId, $rulesRevision);
        $check = $this->chains->checkOf($slice, $ruleCode);
        $this->chains->assertFlow($check, 'joint');
        $chain = $this->chains->chain($slice, $check, $ruleCode, false);
        $bindings = $this->bindings($slice);

        return [
            'slice' => $slice,
            'check' => $check,
            'chain' => $chain,
            'bindings' => $bindings,
            'payload' => $this->rollPayload($bindings),
            'efficiency' => $this->efficiency($check),
        ];
    }


    /**
     * Контексты обеих карточек совместной проверки.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $initiatorRuleCode Проверка инициатора.
     * @param string $opponentRuleCode Проверка противника.
     *
     * @return array{initiator: array<string, mixed>, opponent: array<string, mixed>} Контексты.
     *
     * @throws GameInvalidException Если карточка, режим или пул чужие.
     * @throws CharacterNotFoundException Если ревизии нет.
     */
    private function sideContexts(
        int $spaceId,
        int $rulesRevision,
        string $initiatorRuleCode,
        string $opponentRuleCode,
    ): array {
        $slice = $this->slices->get($spaceId, $rulesRevision);
        $initiatorCheck = $this->chains->checkOf($slice, $initiatorRuleCode);
        $opponentCheck = $this->chains->checkOf($slice, $opponentRuleCode);
        $this->chains->assertFlow($initiatorCheck, 'joint');
        $this->chains->assertFlow($opponentCheck, 'joint');
        $initiatorChain = $this->chains->chain($slice, $initiatorCheck, $initiatorRuleCode, false);
        $opponentChain = $this->chains->chain($slice, $opponentCheck, $opponentRuleCode, false);
        $bindings = $this->bindings($slice);
        $payload = $this->rollPayload($bindings);

        return [
            'initiator' => $this->contextOf($slice, $initiatorCheck, $initiatorChain, $bindings, $payload),
            'opponent' => $this->contextOf($slice, $opponentCheck, $opponentChain, $bindings, $payload),
        ];
    }

    /**
     * Контекст одной стороны.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CheckSpec $check Карточка.
     * @param array<string, mixed> $chain Цепочка.
     * @param array<int, MechanicBinding> $bindings Механики.
     * @param RollMechanicPayload $payload Пул по умолчанию.
     *
     * @return array<string, mixed> Контекст.
     */
    private function contextOf(
        CharacterRuleSlice $slice,
        CheckSpec $check,
        array $chain,
        array $bindings,
        RollMechanicPayload $payload,
    ): array {
        return [
            'slice' => $slice,
            'check' => $check,
            'chain' => $chain,
            'bindings' => $bindings,
            'payload' => $payload,
            'efficiency' => $this->efficiency($check),
        ];
    }


    /**
     * Бросок одной стороны по готовому контексту.
     *
     * @param array<string, mixed> $context Контекст.
     * @param array<string, mixed> $sheet Лист.
     * @param GameCheckThrowOverride|null $override Смена пула.
     *
     * @return RollResult Бросок.
     *
     * @throws GameInvalidException Если пул чужой.
     */
    private function rolledSideOf(array $context, array $sheet, ?GameCheckThrowOverride $override): RollResult
    {
        return $this->rolledSide(
            $context['slice'],
            $context['check'],
            $context['chain'],
            $context['bindings'],
            $context['payload'],
            $context['efficiency'],
            $sheet,
            $override,
        );
    }


    /**
     * Итог совместной проверки.
     *
     * @param RollResult $initiator Бросок инициатора.
     * @param RollResult $opponent Бросок противника.
     * @param string $initiatorLabel Подпись инициатора.
     * @param string $opponentLabel Подпись противника.
     *
     * @return array{initiator: array<string, mixed>, opponent: array<string, mixed>} Итоги.
     */
    private function jointResult(
        RollResult $initiator,
        RollResult $opponent,
        string $initiatorLabel,
        string $opponentLabel,
    ): array {
        return [
            'initiator' => $this->scored(
                ['base' => $opponent->getTotalSuccesses(), 'size' => $opponent->getSpec()->getDieSize()],
                $initiator,
            ) + ['label' => $initiatorLabel],
            'opponent' => $this->scored(
                ['base' => $initiator->getTotalSuccesses(), 'size' => $initiator->getSpec()->getDieSize()],
                $opponent,
            ) + ['label' => $opponentLabel],
        ];
    }


    /**
     * Грани броска из payload.
     *
     * @param RollMechanicPayload $payload Дефолты.
     *
     * @return int Грани.
     *
     * @throws GameInvalidException Если правила броска нет.
     */
    private function dieFaces(RollMechanicPayload $payload): int
    {
        $dieFaces = $payload->getDieFaces();
        if ($dieFaces === null) {
            throw new GameInvalidException('Game check roll rule is invalid');
        }

        return $dieFaces;
    }


    /**
     * Помехи попадания из листа плюс явные.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<int, RollAdvantage> $advantages Явные преимущества.
     *
     * @return array<int, RollAdvantage> Записи.
     */
    private function hitAdvantages(CharacterRuleSlice $slice, array $sheet, array $advantages): array
    {
        return array_merge($this->stateAdvantages($slice, $sheet, null, true), $advantages);
    }


    /**
     * Итог попадания.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $ruleCode Код.
     * @param array{base: int, size: int} $difficulty Трудность.
     * @param DimensionalNumber $mastery Мастерство.
     * @param DimensionalNumber $selectedEfficiency Эффективность.
     * @param RollResult $rolled Бросок.
     *
     * @return array<string, mixed> Итог.
     */
    private function hitResult(
        CharacterRuleSlice $slice,
        string $ruleCode,
        array $difficulty,
        DimensionalNumber $mastery,
        DimensionalNumber $selectedEfficiency,
        RollResult $rolled,
    ): array {
        if ($difficulty['base'] === 0 && $difficulty['size'] === 0) {
            $difficulty = ['base' => 0, 'size' => -1];
        }

        $scored = $this->scored($difficulty, $rolled, new SizedBase(0, -1));
        $scored['poolSize'] = $mastery->getSize();
        $scored['efficiency'] = [
            'base' => $selectedEfficiency->getBase(),
            'size' => $selectedEfficiency->getSize(),
        ];
        $scored['label'] = $slice->findLive($ruleCode)?->getName() ?? 'Попадание';
        $scored['raw'] = $scored['roll'];

        return $scored;
    }


    /**
     * Число кубов, граней и размер успехов. Null не подменяется.
     *
     * @param string|null $characteristic Код или null.
     * @param array<string, mixed> $sheet Лист.
     * @param RollMechanicPayload $payload Дефолты.
     *
     * @return array{diceCount: int, dieFaces: int, size: int} Пул.
     *
     * @throws GameInvalidException Если числа нет.
     */
    private function pool(
        ?string $characteristic,
        array $sheet,
        RollMechanicPayload $payload,
    ): array {
        $dieFaces = $payload->getDieFaces();
        if ($characteristic === null) {
            $diceCount = $payload->getDiceCount();
            $size = 0;
        } else {
            $value = $this->characteristicValue($sheet, $characteristic);
            $diceCount = $value->getBase();
            $size = $value->getSize();
        }

        if ($diceCount === null || $dieFaces === null) {
            throw new GameInvalidException('Game check pool is invalid');
        }

        return ['diceCount' => $diceCount, 'dieFaces' => $dieFaces, 'size' => $size];
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
     * @param SizedBase|null $minimum Нижняя граница размерного roll.
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
            'advantages' => $this->advantages($rolled),
            'min_success_size' => $minimum?->getSize(),
        ];
    }

    /**
     * Записи преимуществ броска.
     *
     * @param RollResult $rolled Бросок.
     *
     * @return array<int, array{source_code: string, source_label: string, delta: int}> Записи.
     */
    private function advantages(RollResult $rolled): array
    {
        return array_map(
            static fn (RollAdvantage $advantage): array => [
                'source_code' => $advantage->getSourceCode(),
                'source_label' => $advantage->getSourceLabel(),
                'delta' => $advantage->getDelta(),
            ],
            $rolled->getSpec()->getAdvantages(),
        );
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
     * @param array<int, RollAdvantage> $advantages Явные преимущества.
     * @param RollAdjustment|null $adjustment Настройка граней и успехов.
     *
     * @return RollResult Результат броска.
     *
     * @throws GameInvalidException Если пул мастерства пуст.
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
        $this->chains->assertFlow($check, $flow);
        $chain = $this->chains->chain($slice, $check, $ruleCode, $forced !== null);
        $bindings = $this->bindings($slice);
        $pool = $this->poolOf($chain['characteristic'], $sheet, $this->rollPayload($bindings), $override);
        $difficulty = $forced ?? $this->difficulty($check, $asked);
        $rolled = $this->rollPool(
            $pool, $slice, $sheet, $chain['characteristic'], $check, $override,
            $this->efficiency($check), $bindings, $chain,
        );

        return $this->scored($difficulty, $rolled, $minimum)
            + ['label' => $slice->findLive($ruleCode)?->getName() ?? ''];
    }

    /**
     * Бросок пула с преимуществами и механиками.
     *
     * @param array{diceCount: int, dieFaces: int, size: int} $pool Пул.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param string|null $characteristic Характеристика.
     * @param CheckSpec $check Карточка.
     * @param GameCheckThrowOverride|null $override Смена пула.
     * @param DimensionalNumber $efficiency Эффективность.
     * @param array<int, MechanicBinding> $bindings Механики.
     * @param array<string, mixed> $chain Цепочка.
     *
     * @return RollResult Бросок.
     */
    private function rollPool(
        array $pool,
        CharacterRuleSlice $slice,
        array $sheet,
        ?string $characteristic,
        CheckSpec $check,
        ?GameCheckThrowOverride $override,
        DimensionalNumber $efficiency,
        array $bindings,
        array $chain,
    ): RollResult {
        return $this->rolls->roll(
            new RollSpec(
                $pool['diceCount'],
                $pool['dieFaces'],
                $override->efficiency ?? $efficiency->getBase(),
                $pool['size'],
                $this->advantagesOf($override, $slice, $sheet, $characteristic, $check->isHitCheck()),
                $override?->efficiency !== null || $this->hasExplicitEfficiency($check),
            ),
            static fn (): float => mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax(),
            $bindings,
            $this->mechanics->getList(),
            new ResolveActiveOptions(null, $chain['attached']),
        );
    }

    /**
     * Пул карточки или смены.
     *
     * @param string|null $characteristic Код цепочки.
     * @param array<string, mixed> $sheet Лист.
     * @param RollMechanicPayload $payload Дефолты.
     * @param GameCheckThrowOverride|null $override Смена или null.
     *
     * @return array{diceCount: int, dieFaces: int, size: int} Пул.
     *
     * @throws GameInvalidException Если числа нет.
     */
    private function poolOf(
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

            return [
                'diceCount' => $override->diceCount,
                'dieFaces' => $dieFaces,
                'size' => $override->size ?? 0,
            ];
        }

        $code = $override->characteristicCode ?? $characteristic;

        return $this->pool($code, $sheet, $payload);
    }

    /**
     * Преимущества свободного пула.
     *
     * @param GameCheckThrowOverride|null $override Смена или null.
     * @param CharacterRuleSlice|null $slice Срез или null.
     * @param array<string, mixed>|null $sheet Лист или null.
     * @param string|null $characteristicCode Характеристика или null.
     * @param bool $hit Попадание.
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
            $source = $override->source ?? 'roll';
            $label = $slice?->findLive($source)?->getName();
            if ($label === null && $source === 'roll') {
                $label = AdvantageSourceLabels::getLabel('roll');
            }

            $rows[] = new RollAdvantage($source, $override->advantage, $label);
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
            array_push($rows, ...$this->stateRows($slice, $state, $characteristicCode, $hit));
        }

        return $rows;
    }

    /**
     * Помехи одного состояния.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param mixed $state Состояние листа.
     * @param string|null $characteristicCode Характеристика проверки или null.
     * @param bool $hit Попадание.
     *
     * @return array<int, RollAdvantage> Записи.
     */
    private function stateRows(
        CharacterRuleSlice $slice,
        mixed $state,
        ?string $characteristicCode,
        bool $hit,
    ): array {
        if (!is_array($state)) {
            return [];
        }

        $code = $state['stateRuleCode'] ?? null;
        $spec = is_string($code) ? $slice->findLive($code)?->getSpec() : null;
        if (!$spec instanceof StateSpec) {
            return [];
        }

        $units = is_int($state['value'] ?? null) ? $state['value'] : 1;
        $rows = [];
        foreach ($spec->getEffects() as $effect) {
            $row = $this->effectRow($slice, $code, $effect, $units, $characteristicCode, $hit);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Помеха одного эффекта или null.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код состояния.
     * @param mixed $effect Эффект.
     * @param int $units Единицы состояния.
     * @param string|null $characteristicCode Характеристика проверки или null.
     * @param bool $hit Попадание.
     *
     * @return RollAdvantage|null Помеха или null.
     */
    private function effectRow(
        CharacterRuleSlice $slice,
        string $code,
        mixed $effect,
        int $units,
        ?string $characteristicCode,
        bool $hit,
    ): ?RollAdvantage {
        if (!$effect instanceof CheckAdvantageEffect || !$this->advantageMatches($slice, $effect, $characteristicCode, $hit)) {
            return null;
        }

        $delta = $effect->getAmount() * ($effect->isPerUnit() ? $units : 1);
        $max = $effect->getMaxAbs();
        if ($max !== null) {
            $delta = max(-$max, min($max, $delta));
        }

        return $delta === 0 ? null : new RollAdvantage($code, $delta, $slice->findLive($code)?->getName());
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

        return $this->dependsOnAny($slice, $characteristicCode, $effect->getCharacteristicCodes());
    }

    /**
     * Характеристика зависит хотя бы от одного корня.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Проверяемая характеристика.
     * @param array<int, string> $roots Корни эффекта.
     *
     * @return bool true, если зависит.
     */
    private function dependsOnAny(CharacterRuleSlice $slice, string $code, array $roots): bool
    {
        foreach ($roots as $root) {
            if ($this->dependsOn($slice, $code, $root)) {
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
     * @param array<string, bool> $seen Уже пройденные коды.
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
     * Значение характеристики из живого листа.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Код характеристики.
     *
     * @return DimensionalNumber Значение.
     *
     * @throws GameInvalidException Если характеристики нет или лист битый.
     */
    private function characteristicValue(array $sheet, string $code): DimensionalNumber
    {
        try {
            $value = $this->contexts->build($sheet)->findCharacteristic($code);
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game check characteristic is invalid', $exception);
        }

        if (!$value instanceof DimensionalNumber) {
            throw new GameInvalidException('Game check characteristic is invalid');
        }

        return $value;
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
