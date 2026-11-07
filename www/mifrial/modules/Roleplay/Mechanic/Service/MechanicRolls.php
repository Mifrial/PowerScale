<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service;

use Closure;
use Mifrial\Roleplay\Mechanic\Constant\RollEvent;
use Mifrial\Roleplay\Mechanic\Dto\CheckRating;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Dto\ResolvedMechanic;
use Mifrial\Roleplay\Mechanic\Dto\RollAdjustment;
use Mifrial\Roleplay\Mechanic\Dto\RollMechanicContext;
use Mifrial\Roleplay\Mechanic\Dto\RollMechanicPayload;
use Mifrial\Roleplay\Mechanic\Dto\RollResult;
use Mifrial\Roleplay\Mechanic\Dto\RollSpec;
use Mifrial\Roleplay\Mechanic\Dto\SizedBase;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls;

/**
 * Бросок на уже существующем движке: дефолты, три события, сравнение итога.
 */
final class MechanicRolls implements IMechanicRolls
{
    private readonly DimensionalCheckNormalizer $normalizer;

    /**
     * Принимает движок.
     *
     * @param IMechanicEngine $engine Событийный движок.
     *
     * @return void
     */
    public function __construct(
        private readonly IMechanicEngine $engine,
    ) {
        $this->normalizer = new DimensionalCheckNormalizer();
    }

    /**
     * Бросает кубы на событиях roll.pool, roll.drop и roll.score.
     *
     * @param RollSpec $spec Параметры броска.
     * @param Closure $rng Источник float в [0, 1).
     * @param array<int, MechanicBinding> $bindings Срезы правил.
     * @param array<int, MechanicRecord> $mechanics Строки каталога.
     * @param ResolveActiveOptions $options Фильтр семейства и доп. коды правил.
     *
     * @return RollResult Итог броска.
     */
    public function roll(
        RollSpec $spec,
        Closure $rng,
        array $bindings,
        array $mechanics,
        ResolveActiveOptions $options,
    ): RollResult {
        $resolved = $spec->withRollDefaults($this->rollPayload($bindings) ?? $this->emptyPayload());
        $context = RollMechanicContext::open($resolved);
        $active = $this->engine->resolveActive($bindings, $mechanics, $this->options($options, $bindings));
        $adjustment = $resolved->getAdjustment();
        $this->rollFaces($context, $rng, $active, $adjustment);
        $this->scoreFaces($context, $active, $adjustment);

        return new RollResult(
            $resolved,
            $context->getRolls(),
            $context->getSuccesses(),
            $context->getAdjustedRolls(),
            $context->getDroppedRolls(),
            $context->getTotalSuccesses(),
            $this->names($context->getApplied(), $mechanics),
        );
    }

    /**
     * Сравнивает успехи и трудность.
     *
     * @param SizedBase $successes Успехи.
     * @param SizedBase $difficulty Трудность.
     * @param int|null $minSize Нижняя граница размера; null — не поднимать.
     *
     * @return CheckRating Прохождение и разность.
     */
    public function rate(SizedBase $successes, SizedBase $difficulty, ?int $minSize = null): CheckRating
    {
        $left = $this->prepare($successes, $minSize);
        $right = $this->prepare($difficulty, $minSize);
        $normalized = $this->normalizer->compare($left, $right);
        $leftBase = $normalized['successes']->getBase();
        $rightBase = $normalized['difficulty']->getBase();

        return new CheckRating($leftBase >= $rightBase, $leftBase - $rightBase);
    }

    /**
     * Проводит пул и сброс граней, затем применяет перестановку карточки.
     *
     * @param RollMechanicContext $context Контекст броска.
     * @param Closure $rng Источник float в [0, 1).
     * @param array<int, ResolvedMechanic> $active Активные механики.
     * @param RollAdjustment|null $adjustment Настройка карточки.
     *
     * @return void
     */
    private function rollFaces(
        RollMechanicContext $context,
        Closure $rng,
        array $active,
        ?RollAdjustment $adjustment,
    ): void {
        $this->engine->runEvent(RollEvent::POOL, $context, $active);
        $context->setRolls($this->faces($rng, $context));
        $this->engine->runEvent(RollEvent::DROP, $context, $active);
        if ($adjustment instanceof RollAdjustment) {
            $context->remapFaces($adjustment->getRemap());
        }
    }

    /**
     * Считает базовые успехи, проводит roll.score и дельты карточки.
     *
     * @param RollMechanicContext $context Контекст броска.
     * @param array<int, ResolvedMechanic> $active Активные механики.
     * @param RollAdjustment|null $adjustment Настройка карточки.
     *
     * @return void
     */
    private function scoreFaces(
        RollMechanicContext $context,
        array $active,
        ?RollAdjustment $adjustment,
    ): void {
        $context->scoreBase();
        $this->engine->runEvent(RollEvent::SCORE, $context, $active);
        if ($adjustment instanceof RollAdjustment) {
            $this->applyScoreDeltas($context, $adjustment);
        }

        $context->sumSuccesses();
    }

    /**
     * Дельты успехов карточки поверх правила 6 и 1.
     *
     * Единица даёт oneDelta, максимальная грань — faceDelta, но только если она
     * выше эффективности: при эффективности на грани она уже успех.
     *
     * @param RollMechanicContext $context Контекст после roll.score.
     * @param RollAdjustment $adjustment Настройка карточки.
     *
     * @return void
     */
    private function applyScoreDeltas(RollMechanicContext $context, RollAdjustment $adjustment): void
    {
        $oneDelta = $adjustment->getOneDelta();
        $faceDelta = $adjustment->getFaceDelta();
        if ($oneDelta === 0 && $faceDelta === 0) {
            return;
        }

        foreach ($context->getAdjustedRolls() as $index => $face) {
            $delta = $this->faceDelta($context, $face, $oneDelta, $faceDelta);
            if ($delta !== 0) {
                $context->addSuccess($index, $delta);
            }
        }
    }

    /**
     * Дельта одной грани поверх правила 6 и 1.
     *
     * @param RollMechanicContext $context Контекст броска.
     * @param int $face Выпавшая грань.
     * @param int $oneDelta Дельта за единицу.
     * @param int $faceDelta Дельта за максимальную грань.
     *
     * @return int Суммарная дельта.
     */
    private function faceDelta(
        RollMechanicContext $context,
        int $face,
        int $oneDelta,
        int $faceDelta,
    ): int {
        $delta = 0;
        if ($face === 1) {
            $delta += $oneDelta;
        }

        if ($face === $context->getDieFaces() && $face > $context->getEfficiency()) {
            $delta += $faceDelta;
        }

        return $delta;
    }

    /**
     * Первый payload «Бросок» в порядке binding.
     *
     * @param array<int, MechanicBinding> $bindings Срезы.
     *
     * @return RollMechanicPayload|null Payload или null.
     */
    private function rollPayload(array $bindings): ?RollMechanicPayload
    {
        foreach ($bindings as $binding) {
            $payload = $binding->getMechanicPayload();
            if ($payload instanceof RollMechanicPayload) {
                return $payload;
            }
        }

        return null;
    }

    /**
     * Пустой payload: дефолты спеки не меняются.
     *
     * @return RollMechanicPayload Payload без полей.
     */
    private function emptyPayload(): RollMechanicPayload
    {
        return new RollMechanicPayload();
    }

    /**
     * Подставляет sub_mechanics, если фильтр не задан.
     *
     * @param ResolveActiveOptions $options Опции вызывающего.
     * @param array<int, MechanicBinding> $bindings Срезы.
     *
     * @return ResolveActiveOptions Опции для resolveActive.
     */
    private function options(ResolveActiveOptions $options, array $bindings): ResolveActiveOptions
    {
        if ($options->getIncludeCodes() !== null) {
            return $options;
        }

        $subMechanics = $this->rollPayload($bindings)?->getSubMechanics();
        if ($subMechanics === null) {
            return $options;
        }

        return new ResolveActiveOptions($subMechanics, $options->getExtraRuleCodes());
    }

    /**
     * Грани: floor(rng * dieFaces) + 1, не короче одного куба.
     *
     * @param Closure $rng Источник float.
     * @param RollMechanicContext $context Пул и число граней.
     *
     * @return array<int, int> Грани.
     */
    private function faces(Closure $rng, RollMechanicContext $context): array
    {
        $faces = [];
        $count = max(1, $context->getPoolSize());
        for ($index = 0; $index < $count; $index++) {
            $faces[] = (int) floor($rng() * $context->getDieFaces()) + 1;
        }

        return $faces;
    }

    /**
     * Имена по коду. Повтор кода оставляет последнюю строку каталога.
     *
     * @param array<int, string> $applied Коды.
     * @param array<int, MechanicRecord> $mechanics Каталог.
     *
     * @return array<int, string>|null Имена или null.
     */
    private function names(array $applied, array $mechanics): ?array
    {
        if ($applied === []) {
            return null;
        }

        $byCode = $this->namesByCode($mechanics);
        $names = [];
        foreach ($applied as $code) {
            $names[] = $byCode[$code] ?? $code;
        }

        return $names;
    }

    /**
     * Индекс имён. Последняя строка с тем же кодом побеждает.
     *
     * @param array<int, MechanicRecord> $mechanics Каталог.
     *
     * @return array<string, string> Код → имя.
     */
    private function namesByCode(array $mechanics): array
    {
        $byCode = [];
        foreach ($mechanics as $mechanic) {
            $byCode[$mechanic->getCode()] = $mechanic->getName();
        }

        return $byCode;
    }

    /**
     * Сворачивает отрицательную базу и поднимает размер.
     *
     * @param SizedBase $value Сторона сравнения.
     * @param int|null $minSize Нижняя граница или null.
     *
     * @return SizedBase Подготовленная пара.
     */
    private function prepare(SizedBase $value, ?int $minSize): SizedBase
    {
        $folded = $value->foldNegative();
        if ($minSize === null) {
            return $folded;
        }

        return $folded->raiseToMinSize($minSize);
    }
}
