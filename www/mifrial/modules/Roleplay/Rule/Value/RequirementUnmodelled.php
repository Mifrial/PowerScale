<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Value;

/**
 * Факты, которых пока нет в модели PHP: скорость и магия. null — данных нет.
 */
final class RequirementUnmodelled
{
    /**
     * Создаёт факты.
     *
     * @param array<string, array{direction: ?string, stepsPerActionPoint: int}>|null $speed Скорость по осям или null.
     * @param array<int, string>|null $magicPaths Пути волшебства или null.
     * @param array<string, int>|null $magicPathExperience Опыт путей или null.
     * @param array<int, array{keywords: array<int, string>, cost: int}>|null $keywordAbilities
     *        Взятые способности с их признаками и ценой или null.
     *
     * @return void
     */
    public function __construct(
        public readonly ?array $speed = null,
        public readonly ?array $magicPaths = null,
        public readonly ?array $magicPathExperience = null,
        public readonly ?array $keywordAbilities = null,
    ) {
    }
}
