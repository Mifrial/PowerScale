<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Constant;

/**
 * События потока броска: пул, сброс, подсчёт.
 */
final class RollEvent
{
    /**
     * До броска: добавить кубы в пул.
     */
    public const POOL = 'roll.pool';

    /**
     * После броска: убрать крайние грани.
     */
    public const DROP = 'roll.drop';

    /**
     * После базового подсчёта: дельты успехов.
     */
    public const SCORE = 'roll.score';
}
