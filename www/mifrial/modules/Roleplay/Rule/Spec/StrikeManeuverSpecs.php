<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ManeuverBoost;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StrikeManeuver;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StrikeManeuverEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StrikeManeuverEffectSelector;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Узлы strike_maneuver и maneuver_boost.
 *
 * Узел описывает смысл данных: условие прошлого удара, мастерство оружия и
 * типизированные эффекты. Кодов карточек здесь нет.
 */
final class StrikeManeuverSpecs
{
    /**
     * Виды эффектов.
     *
     * @var array<int, string>
     */
    private const EFFECT_KINDS = [StrikeManeuverEffect::KIND_STATE];

    /**
     * Читает узел strike_maneuver или null, если его нет.
     *
     * @param array<string|int, mixed> $document Документ способности.
     *
     * @return StrikeManeuver|null Манёвр или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function read(array $document): ?StrikeManeuver
    {
        $row = SpecShape::object($document, 'strike_maneuver');
        if ($row === null) {
            return null;
        }

        $minSuccess = SpecShape::int($row, 'min_success');
        if ($minSuccess < 1) {
            throw new RuleSpecShapeException('strike_maneuver.min_success');
        }

        $minMastery = SpecShape::optionalInt($row, 'min_weapon_mastery');
        if ($minMastery !== null && $minMastery < 1) {
            throw new RuleSpecShapeException('strike_maneuver.min_weapon_mastery');
        }

        return new StrikeManeuver(
            $minSuccess,
            $minMastery,
            self::effects($row, 'strike_maneuver.effects'),
        );
    }

    /**
     * Читает узел maneuver_boost или null, если его нет.
     *
     * @param array<string|int, mixed> $document Документ способности.
     *
     * @return ManeuverBoost|null Усиление или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function boost(array $document): ?ManeuverBoost
    {
        $row = SpecShape::object($document, 'maneuver_boost');
        if ($row === null) {
            return null;
        }

        $maneuvers = self::codes($row, 'maneuvers');
        $minSuccess = SpecShape::int($row, 'min_success');
        if ($minSuccess < 1) {
            throw new RuleSpecShapeException('maneuver_boost.min_success');
        }

        $minMastery = SpecShape::optionalInt($row, 'min_weapon_mastery');
        if ($minMastery !== null && $minMastery < 1) {
            throw new RuleSpecShapeException('maneuver_boost.min_weapon_mastery');
        }

        return new ManeuverBoost(
            $maneuvers,
            $minSuccess,
            $minMastery,
            self::replaces($row),
            self::effects($row, 'maneuver_boost.effects'),
        );
    }

    /**
     * Непустой список кодов без дублей.
     *
     * @param array<string, mixed> $row Узел.
     * @param string $key Поле.
     *
     * @return array<int, string> Коды.
     *
     * @throws RuleSpecShapeException Если список пуст или содержит пустой код.
     */
    private static function codes(array $row, string $key): array
    {
        $codes = [];
        foreach (SpecShape::stringList($row, $key) as $code) {
            if ($code === '') {
                throw new RuleSpecShapeException('maneuver_boost.' . $key);
            }

            if (!in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        if ($codes === []) {
            throw new RuleSpecShapeException('maneuver_boost.' . $key);
        }

        return $codes;
    }

    /**
     * Типизированные эффекты узла.
     *
     * @param array<string, mixed> $row Узел.
     * @param string $path Путь для ошибки.
     *
     * @return array<int, StrikeManeuverEffect> Эффекты.
     *
     * @throws RuleSpecShapeException Если эффект чужой или список пуст.
     */
    private static function effects(array $row, string $path): array
    {
        $effects = [];
        foreach (SpecShape::list($row, 'effects') as $entry) {
            $entry = SpecShape::requireMap($entry, $path);
            $kind = self::kind($entry, $path);
            $state = SpecShape::string($entry, 'state');
            if ($state === '') {
                throw new RuleSpecShapeException($path . '.state');
            }

            $amount = SpecShape::int($entry, 'amount');
            if ($amount < 1) {
                throw new RuleSpecShapeException($path . '.amount');
            }

            $effects[] = new StrikeManeuverEffect($kind, $state, $amount);
        }

        if ($effects === []) {
            throw new RuleSpecShapeException($path);
        }

        return $effects;
    }

    /**
     * Селекторы заменяемых эффектов.
     *
     * @param array<string, mixed> $row Узел.
     *
     * @return array<int, StrikeManeuverEffectSelector> Селекторы.
     *
     * @throws RuleSpecShapeException Если селектор чужой.
     */
    private static function replaces(array $row): array
    {
        $selectors = [];
        foreach (SpecShape::list($row, 'replaces') as $entry) {
            $entry = SpecShape::requireMap($entry, 'maneuver_boost.replaces');
            $kind = self::kind($entry, 'maneuver_boost.replaces');
            $state = SpecShape::string($entry, 'state');
            if ($state === '') {
                throw new RuleSpecShapeException('maneuver_boost.replaces.state');
            }

            $selectors[] = new StrikeManeuverEffectSelector($kind, $state);
        }

        return $selectors;
    }

    /**
     * Вид эффекта из закрытого списка.
     *
     * @param array<string, mixed> $row Узел.
     * @param string $path Путь для ошибки.
     *
     * @return string Вид.
     *
     * @throws RuleSpecShapeException Если вид чужой.
     */
    private static function kind(array $row, string $path): string
    {
        $kind = SpecShape::string($row, 'kind');
        if (!in_array($kind, self::EFFECT_KINDS, true)) {
            throw new RuleSpecShapeException($path . '.kind');
        }

        return $kind;
    }
}
