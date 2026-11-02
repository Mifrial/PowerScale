<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Режим исполнения шага процесса.
 */
final class ProcessExecution
{
    public const TYPE_STRIKE = 'strike';

    public const TYPE_ACTION = 'action';

    public const TYPE_NONE = 'none';

    /**
     * Создаёт режим.
     *
     * @param string $type Strike, action или none.
     * @param string|null $actionCode Карточка действия или null.
     *
     * @return void
     */
    public function __construct(
        private readonly string $type,
        private readonly ?string $actionCode = null,
    ) {
    }

    /**
     * Режим.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Карточка действия.
     *
     * @return string|null Код или null.
     */
    public function getActionCode(): ?string
    {
        return $this->actionCode;
    }

    /**
     * Удар ли это.
     *
     * @return bool true для strike.
     */
    public function isStrike(): bool
    {
        return $this->type === self::TYPE_STRIKE;
    }

    /**
     * Неатакующее действие ли это.
     *
     * @return bool true для action.
     */
    public function isAction(): bool
    {
        return $this->type === self::TYPE_ACTION;
    }

    /**
     * Шаг без действия ли это.
     *
     * @return bool true для none.
     */
    public function isNone(): bool
    {
        return $this->type === self::TYPE_NONE;
    }
}
