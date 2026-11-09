<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.castSpell.
 */
final class CastGameSpellInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ повтора.
     * @param int $expectedVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа кастера.
     * @param array $actor Кастер.
     * @param string $spellCode Заклинание.
     * @param string $pathCode Путь.
     * @param array $parameters Активационные параметры.
     * @param array $usedPower Источник и значение используемой мощи.
     * @param int|null $expectedTargetVersion Версия листа цели.
     * @param array|null $target Цель.
     * @param int $distanceIpari Дистанция в ипари.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly int $battleId,
        public readonly string $idempotencyKey,
        public readonly int $expectedVersion,
        public readonly int $expectedSheetVersion,
        public readonly array $actor,
        public readonly string $spellCode,
        public readonly string $pathCode,
        public readonly array $parameters,
        public readonly array $usedPower,
        public readonly ?int $expectedTargetVersion = null,
        public readonly ?array $target = null,
        public readonly int $distanceIpari = 0,
    ) {
    }
}
