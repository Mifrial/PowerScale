<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CoverAlly;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\DefenseAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ManeuverCheck;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ManeuverMark;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ManeuverModifier;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Прикрытие союзника и обманный манёвр: узлы cover_ally и maneuver.
 */
final class AbilityTactics
{
    /**
     * Литералы узла maneuver.
     *
     * @var array<string, string>
     */
    private const MANEUVER_ROW = [
        'trigger' => ManeuverModifier::TRIGGER_STRIKE,
        'window' => ManeuverModifier::WINDOW_AFTER_DEFENSE_BEFORE_CHECK,
        'attack' => ManeuverModifier::ATTACK_AUTO_FAIL,
    ];

    /**
     * Литерал исхода манёвра.
     *
     * @var array<string, string>
     */
    private const MANEUVER_OUTCOME = [
        'on_target_passed' => ManeuverModifier::OUTCOME_MAY_IGNORE,
    ];

    /**
     * Литерал вида метки.
     *
     * @var array<string, string>
     */
    private const MANEUVER_MARK_KIND = [
        'kind' => ManeuverModifier::MARK_KIND_ADVANTAGE,
    ];

    /**
     * Литералы области метки.
     *
     * @var array<string, string>
     */
    private const MANEUVER_MARK_SCOPE = [
        'scope' => ManeuverModifier::SCOPE_DEFENSE_AGAINST_ATTACKER,
        'consumes' => ManeuverModifier::CONSUMES_FIRST_DEFENSE,
        'expires' => ManeuverModifier::EXPIRES_ATTACKER_TURN,
    ];

    /**
     * Прикрытие.
     *
     * Старая форма `circumstance_delta` отклоняется: источник и величина
     * должны быть раздельными полями.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return CoverAlly|null Прикрытие или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function cover(array $document): ?CoverAlly
    {
        $row = SpecShape::object($document, 'cover_ally');
        if ($row === null) {
            return null;
        }

        if (array_key_exists('circumstance_delta', $row)) {
            throw new RuleSpecShapeException('cover_ally.circumstance_delta');
        }

        return new CoverAlly(
            self::coverAdvantages($row),
            self::coverReactions($row),
            self::coverResolution($row),
            SpecShape::optionalString($row, 'distance'),
        );
    }

    /**
     * Обманный манёвр.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return ManeuverModifier|null Манёвр или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function maneuver(array $document): ?ManeuverModifier
    {
        $row = SpecShape::object($document, 'maneuver');
        if ($row === null) {
            return null;
        }

        self::assertManeuverRow($row);
        $checks = self::maneuverChecksBlock($row);
        $outcome = SpecShape::object($row, 'outcome');
        if ($outcome === null) {
            throw new RuleSpecShapeException('maneuver.outcome');
        }

        self::assertConstants($outcome, self::MANEUVER_OUTCOME, 'maneuver.outcome');

        return new ManeuverModifier(
            ManeuverModifier::TRIGGER_STRIKE,
            ManeuverModifier::WINDOW_AFTER_DEFENSE_BEFORE_CHECK,
            ManeuverModifier::ATTACK_AUTO_FAIL,
            $checks['attacker'],
            $checks['target'],
            true,
            self::maneuverMarks($outcome),
        );
    }

    /**
     * Помехи защите прикрывающего.
     *
     * @param array<string, mixed> $row Узел cover_ally.
     *
     * @return array<int, DefenseAdvantage> Помехи.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function coverAdvantages(array $row): array
    {
        $advantages = [];
        foreach (SpecShape::list($row, 'defense_advantages') as $entry) {
            $entry = SpecShape::requireMap($entry, 'cover_ally.defense_advantages');
            $source = SpecShape::optionalString($entry, 'source_code');
            if ($source === null || trim($source) === '') {
                throw new RuleSpecShapeException('cover_ally.defense_advantages.source_code');
            }

            $advantages[] = new DefenseAdvantage(SpecShape::int($entry, 'delta'), $source);
        }

        return $advantages;
    }

    /**
     * Разрешённые защиты прикрытия.
     *
     * @param array<string, mixed> $row Узел cover_ally.
     *
     * @return array<int, string> Коды реакций.
     *
     * @throws RuleSpecShapeException Если список пуст или содержит пустой код.
     */
    private static function coverReactions(array $row): array
    {
        $reactions = SpecShape::stringList($row, 'reactions');
        if ($reactions === [] || in_array('', $reactions, true)) {
            throw new RuleSpecShapeException('cover_ally.reactions');
        }

        return $reactions;
    }

    /**
     * Стратегии исхода прикрытия.
     *
     * @param array<string, mixed> $row Узел cover_ally.
     *
     * @return array{success: string, failure: string, becomes: string} Стратегии.
     *
     * @throws RuleSpecShapeException Если стратегии чужие.
     */
    private static function coverResolution(array $row): array
    {
        $resolution = SpecShape::requireMap($row['resolution'] ?? null, 'cover_ally.resolution');
        $success = SpecShape::optionalString($resolution, 'success');
        $failure = SpecShape::optionalString($resolution, 'failure');
        $becomes = SpecShape::optionalString($resolution, 'becomes');
        if (
            $success !== CoverAlly::SUCCESS_HIGHEST_RATING
            || $failure !== CoverAlly::FAILURE_ATTACKER_CHOICE
            || $becomes !== CoverAlly::BECOMES_ACTUAL_TARGET
        ) {
            throw new RuleSpecShapeException('cover_ally.resolution');
        }

        return [
            'success' => (string) $success,
            'failure' => (string) $failure,
            'becomes' => (string) $becomes,
        ];
    }

    /**
     * Строковые поля узла против ожидаемых литералов.
     *
     * @param array<string, mixed> $row Узел spec.
     * @param array<string, string> $expected Поле → литерал.
     * @param string $path Префикс пути для ошибки.
     *
     * @return void
     *
     * @throws RuleSpecShapeException Если значение чужое.
     */
    private static function assertConstants(array $row, array $expected, string $path): void
    {
        foreach ($expected as $key => $value) {
            if (SpecShape::string($row, $key) !== $value) {
                throw new RuleSpecShapeException($path . '.' . $key);
            }
        }
    }

    /**
     * Литералы узла maneuver.
     *
     * @param array<string, mixed> $row Узел maneuver.
     *
     * @return void
     *
     * @throws RuleSpecShapeException Если литерал чужой.
     */
    private static function assertManeuverRow(array $row): void
    {
        self::assertConstants($row, self::MANEUVER_ROW, 'maneuver');
    }

    /**
     * Блок проверок обеих сторон манёвра.
     *
     * @param array<string, mixed> $row Узел maneuver.
     *
     * @return array{attacker: array<int, ManeuverCheck>, target: array<int, ManeuverCheck>} Пары.
     *
     * @throws RuleSpecShapeException Если проверок нет.
     */
    private static function maneuverChecksBlock(array $row): array
    {
        $checks = SpecShape::object($row, 'check');
        if ($checks === null) {
            throw new RuleSpecShapeException('maneuver.check');
        }

        $attacker = self::maneuverChecks($checks, 'attacker');
        $target = self::maneuverChecks($checks, 'target');
        if ($attacker === [] || $target === []) {
            throw new RuleSpecShapeException('maneuver.check');
        }

        return ['attacker' => $attacker, 'target' => $target];
    }

    /**
     * Метки не распознавшим манёвр.
     *
     * @param array<string, mixed> $outcome Узел outcome.
     *
     * @return array<int, ManeuverMark> Метки.
     *
     * @throws RuleSpecShapeException Если меток нет.
     */
    private static function maneuverMarks(array $outcome): array
    {
        $marks = [];
        foreach (SpecShape::list($outcome, 'on_target_failed') as $entry) {
            $marks[] = self::maneuverMark($entry);
        }

        if ($marks === []) {
            throw new RuleSpecShapeException('maneuver.outcome.on_target_failed');
        }

        return $marks;
    }

    /**
     * Одна метка не распознавшего манёвр.
     *
     * @param mixed $entry Строка.
     *
     * @return ManeuverMark Метка.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function maneuverMark(mixed $entry): ManeuverMark
    {
        $entry = SpecShape::requireMap($entry, 'maneuver.outcome.on_target_failed');
        self::assertConstants($entry, self::MANEUVER_MARK_KIND, 'maneuver.outcome.on_target_failed');
        $value = SpecShape::int($entry, 'value');
        if ($value === 0) {
            throw new RuleSpecShapeException('maneuver.outcome.on_target_failed.value');
        }

        $source = SpecShape::string($entry, 'source_code');
        if ($source === '') {
            throw new RuleSpecShapeException('maneuver.outcome.on_target_failed.source_code');
        }

        self::assertConstants($entry, self::MANEUVER_MARK_SCOPE, 'maneuver.outcome.on_target_failed');

        return new ManeuverMark(
            ManeuverModifier::MARK_KIND_ADVANTAGE,
            $value,
            $source,
            ManeuverModifier::SCOPE_DEFENSE_AGAINST_ATTACKER,
            ManeuverModifier::CONSUMES_FIRST_DEFENSE,
            ManeuverModifier::EXPIRES_ATTACKER_TURN,
        );
    }

    /**
     * Пары проверок одной стороны манёвра.
     *
     * @param array<string, mixed> $checks Узел check.
     * @param string $side Сторона.
     *
     * @return array<int, ManeuverCheck> Пары.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function maneuverChecks(array $checks, string $side): array
    {
        $rows = [];
        foreach (SpecShape::list($checks, $side) as $entry) {
            $entry = SpecShape::requireMap($entry, 'maneuver.check.' . $side);
            $check = SpecShape::string($entry, 'check');
            $characteristic = SpecShape::string($entry, 'characteristic');
            if ($check === '' || $characteristic === '') {
                throw new RuleSpecShapeException('maneuver.check.' . $side);
            }

            $rows[] = new ManeuverCheck($check, $characteristic);
        }

        return $rows;
    }
}
