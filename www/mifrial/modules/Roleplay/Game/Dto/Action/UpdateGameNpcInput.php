<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Поля JSON update NPC, не порты DI.

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.updateNpc. Ревизию листа не двигает.
 */
final class UpdateGameNpcInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     * @param string $name Имя.
     * @param array<string, mixed> $visibility Видимость.
     * @param array<string, mixed> $choices Документ.
     * @param array<string, mixed> $sheet Сверка.
     * @param int $expectedNpcActualVersion CAS.
     * @param int|null $spaceId Сверка мира.
     * @param string|null $spaceCode Сверка кода.
     * @param int|null $rulesRevision Сверка ревизии листа.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly int $npcId,
        public readonly string $name,
        public readonly array $visibility,
        public readonly array $choices,
        public readonly array $sheet,
        public readonly int $expectedNpcActualVersion,
        public readonly ?int $spaceId = null,
        public readonly ?string $spaceCode = null,
        public readonly ?int $rulesRevision = null,
    ) {
    }
}
