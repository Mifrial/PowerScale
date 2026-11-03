<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Когда карточка-реакция может быть применена.
 */
final class ReactionModifier
{
    /**
     * Открытый удар.
     */
    public const TRIGGER_STRIKE = 'strike';

    /**
     * До ответа цели.
     */
    public const WINDOW_BEFORE_DEFENSE = 'before_defense';

    /**
     * Третья сторона: не атакующий и не цель.
     */
    public const PERFORMER_THIRD_PARTY = 'third_party';

    /**
     * Защищающийся.
     */
    public const PERFORMER_DEFENDER = 'defender';

    /**
     * Любой участник боя.
     */
    public const PERFORMER_ANY = 'any';

    /**
     * Создаёт узел реакции.
     *
     * @param string $trigger Событие, открывающее реакцию.
     * @param string $window Момент внутри события.
     * @param string $performer Кто может объявить реакцию.
     *
     * @return void
     */
    public function __construct(
        private readonly string $trigger,
        private readonly string $window,
        private readonly string $performer,
    ) {
    }

    /**
     * Событие.
     *
     * @return string Код события.
     */
    public function getTrigger(): string
    {
        return $this->trigger;
    }

    /**
     * Момент.
     *
     * @return string Код момента.
     */
    public function getWindow(): string
    {
        return $this->window;
    }

    /**
     * Кто объявляет.
     *
     * @return string Роль в ударе.
     */
    public function getPerformer(): string
    {
        return $this->performer;
    }

    /**
     * Разрешена ли роль в текущем событии.
     *
     * @param string $role Attacker, defender или third_party.
     *
     * @return bool true, если реакция доступна.
     */
    public function allows(string $role): bool
    {
        return $this->performer === self::PERFORMER_ANY || $this->performer === $role;
    }
}
