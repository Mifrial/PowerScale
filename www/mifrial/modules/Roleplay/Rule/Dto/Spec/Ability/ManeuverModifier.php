<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Поля узла манёвра, не порты DI.

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Узел maneuver: обманная атака с совместной проверкой и меткой.
 */
final class ManeuverModifier
{
    public const TRIGGER_STRIKE = 'strike';

    public const WINDOW_AFTER_DEFENSE_BEFORE_CHECK = 'after_defense_before_check';

    public const ATTACK_AUTO_FAIL = 'auto_fail';

    public const OUTCOME_MAY_IGNORE = 'may_ignore';

    public const MARK_KIND_ADVANTAGE = 'advantage';

    public const SCOPE_DEFENSE_AGAINST_ATTACKER = 'defense_against_attacker';

    public const CONSUMES_FIRST_DEFENSE = 'first_defense';

    public const EXPIRES_ATTACKER_TURN = 'attacker_turn';

    /**
     * Создаёт описание манёвра.
     *
     * @param string $trigger Событие.
     * @param string $window Момент.
     * @param string $attack Поведение атаки.
     * @param array<int, ManeuverCheck> $attackerChecks Проверки атакующего.
     * @param array<int, ManeuverCheck> $targetChecks Проверки цели.
     * @param bool $mayIgnore Можно отказаться после победы.
     * @param array<int, ManeuverMark> $marks Метки не распознавшим.
     *
     * @return void
     */
    public function __construct(
        private readonly string $trigger,
        private readonly string $window,
        private readonly string $attack,
        private readonly array $attackerChecks,
        private readonly array $targetChecks,
        private readonly bool $mayIgnore,
        private readonly array $marks,
    ) {
    }

    /**
     * Событие.
     *
     * @return string Код.
     */
    public function getTrigger(): string
    {
        return $this->trigger;
    }

    /**
     * Момент.
     *
     * @return string Код.
     */
    public function getWindow(): string
    {
        return $this->window;
    }

    /**
     * Поведение атаки.
     *
     * @return string Код.
     */
    public function getAttack(): string
    {
        return $this->attack;
    }

    /**
     * Проверки атакующего.
     *
     * @return array<int, ManeuverCheck> Пары.
     */
    public function getAttackerChecks(): array
    {
        return $this->attackerChecks;
    }

    /**
     * Проверки цели.
     *
     * @return array<int, ManeuverCheck> Пары.
     */
    public function getTargetChecks(): array
    {
        return $this->targetChecks;
    }

    /**
     * Можно ли отказаться после победы.
     *
     * @return bool Признак.
     */
    public function mayIgnore(): bool
    {
        return $this->mayIgnore;
    }

    /**
     * Метки не распознавшим.
     *
     * @return array<int, ManeuverMark> Метки.
     */
    public function getMarks(): array
    {
        return $this->marks;
    }

    /**
     * JSON-форма для строки удара.
     *
     * @return array<string, mixed> Описание.
     */
    public function toArray(): array
    {
        return [
            'trigger' => $this->trigger,
            'window' => $this->window,
            'attack' => $this->attack,
            'attacker' => array_map(
                static fn (ManeuverCheck $check): array => $check->toArray(),
                $this->attackerChecks,
            ),
            'target' => array_map(
                static fn (ManeuverCheck $check): array => $check->toArray(),
                $this->targetChecks,
            ),
            'may_ignore' => $this->mayIgnore,
            'marks' => array_map(
                static fn (ManeuverMark $mark): array => $mark->toArray(),
                $this->marks,
            ),
        ];
    }
}
