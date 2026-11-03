<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ReactionModifier;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Узел reaction: когда карточка может быть объявлена реакцией.
 */
final class ReactionSpecs
{
    /**
     * Разрешённые события.
     *
     * @var array<int, string>
     */
    private const TRIGGERS = [ReactionModifier::TRIGGER_STRIKE];

    /**
     * Разрешённые моменты.
     *
     * @var array<int, string>
     */
    private const WINDOWS = [ReactionModifier::WINDOW_BEFORE_DEFENSE];

    /**
     * Разрешённые роли.
     *
     * @var array<int, string>
     */
    private const PERFORMERS = [
        ReactionModifier::PERFORMER_THIRD_PARTY,
        ReactionModifier::PERFORMER_DEFENDER,
        ReactionModifier::PERFORMER_ANY,
    ];

    /**
     * Читает узел reaction или null, если его нет.
     *
     * @param array<string|int, mixed> $document Документ способности.
     *
     * @return ReactionModifier|null Реакция или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function read(array $document): ?ReactionModifier
    {
        $row = SpecShape::object($document, 'reaction');
        if ($row === null) {
            return null;
        }

        $trigger = SpecShape::string($row, 'trigger');
        if (!in_array($trigger, self::TRIGGERS, true)) {
            throw new RuleSpecShapeException('reaction.trigger');
        }

        $window = SpecShape::string($row, 'window');
        if (!in_array($window, self::WINDOWS, true)) {
            throw new RuleSpecShapeException('reaction.window');
        }

        $performer = SpecShape::string($row, 'performer');
        if (!in_array($performer, self::PERFORMERS, true)) {
            throw new RuleSpecShapeException('reaction.performer');
        }

        return new ReactionModifier($trigger, $window, $performer);
    }
}
