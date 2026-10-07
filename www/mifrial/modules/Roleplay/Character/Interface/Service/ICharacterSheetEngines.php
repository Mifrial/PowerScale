<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Interface\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;

/**
 * Сборка и ремап листа без записи в character.
 */
interface ICharacterSheetEngines
{
    /**
     * Собирает лист на ревизии. Пустая раса не становится problem.
     *
     * @param array<string, mixed> $choices Документ choices.
     * @param int $spaceId Мир.
     * @param int $revision Ревизия.
     * @param array<string, mixed>|null $expectedSheet Сверка или null.
     *
     * @return array<string, mixed> kind, problems, revision, choices, sheet, name.
     *
     * @throws ActionException INVALID_PARAMS.
     * @throws CharacterNotFoundException Если нет ревизии.
     */
    public function build(array $choices, int $spaceId, int $revision, ?array $expectedSheet): array;

    /**
     * Ремапит choices по code и собирает лист целевой ревизии.
     *
     * @param array<string, mixed> $choices Документ choices.
     * @param int $spaceId Мир.
     * @param int $sourceRevision Исходная ревизия.
     * @param int $targetRevision Целевая ревизия.
     *
     * @return array<string, mixed> kind, problems, revision, choices, sheet, name.
     *
     * @throws ActionException INVALID_PARAMS.
     * @throws CharacterNotFoundException Если нет ревизии.
     */
    public function remap(array $choices, int $spaceId, int $sourceRevision, int $targetRevision): array;
}
