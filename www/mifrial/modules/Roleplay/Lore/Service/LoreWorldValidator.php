<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

use Mifrial\Roleplay\Lore\Dto\LoreWorldValues;
use Mifrial\Roleplay\Lore\Exception\LoreInvalidException;

/**
 * Проверка снимка мира.
 */
final class LoreWorldValidator
{
    private const ALLOWED_KEYS = ['code', 'name', 'description', 'visibility', 'spaceCode'];

    private const VISIBILITIES = ['public', 'private'];

    /**
     * Проверяет снимок мира.
     *
     * @param array<string|int, mixed> $payload Снимок.
     *
     * @return LoreWorldValues Поля для записи.
     *
     * @throws LoreInvalidException Если снимок недопустим.
     */
    public function validate(array $payload): LoreWorldValues
    {
        LorePayload::assertKnownKeys($payload, self::ALLOWED_KEYS);
        $code = LoreCode::requireValid(trim(LorePayload::requireString($payload, 'code')), 'Lore world code');
        $name = trim(LorePayload::requireString($payload, 'name'));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 255) {
            throw new LoreInvalidException('Lore world name must be 1..255 characters');
        }

        $description = LorePayload::optionalString($payload, 'description');
        if (mb_strlen($description, 'UTF-8') > 20000) {
            throw new LoreInvalidException('Lore world description is too long');
        }

        $visibility = LorePayload::optionalString($payload, 'visibility', 'private');
        if (!in_array($visibility, self::VISIBILITIES, true)) {
            throw new LoreInvalidException('Lore world visibility must be public or private');
        }

        $spaceCode = trim(LorePayload::optionalString($payload, 'spaceCode'));
        if (mb_strlen($spaceCode, 'UTF-8') > 64) {
            throw new LoreInvalidException('Lore world spaceCode is too long');
        }

        return new LoreWorldValues($code, $name, $description, $visibility, $spaceCode);
    }
}
