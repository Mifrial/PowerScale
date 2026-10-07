<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use JsonException;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Один semantic diff snapshot к actual. Второй алгоритм не заводится.
 */
final class GameCharacterDiff
{
    /**
     * Есть отличие листа от копии. Нет копии — отличие есть.
     *
     * @param array<string, mixed>|null $snapshot Копия или null.
     * @param CharacterRecord $actual Текущий лист.
     *
     * @return bool true, если модерация нужна из-за diff.
     *
     * @throws GameInvalidException Если JSON копии не сериализуется.
     */
    public function hasChanges(?array $snapshot, CharacterRecord $actual): bool
    {
        if ($snapshot === null) {
            return true;
        }

        return $this->canonical($snapshot['name'] ?? null) !== $this->canonical($actual->getName())
            || $this->canonical($snapshot['rulesRevision'] ?? null) !== $this->canonical($actual->getRulesRevision())
            || $this->canonical($snapshot['choices'] ?? null) !== $this->canonical($actual->getChoices())
            || $this->canonical($snapshot['sheet'] ?? null) !== $this->canonical($actual->getSheet());
    }

    /**
     * Тонкая обёртка над hasChanges.
     *
     * @param array<string, mixed>|null $snapshot Копия или null.
     * @param CharacterRecord $actual Текущий лист.
     *
     * @return bool true, если hasChanges.
     *
     * @throws GameInvalidException Если JSON копии не сериализуется.
     */
    public function isChanged(?array $snapshot, CharacterRecord $actual): bool
    {
        return $this->hasChanges($snapshot, $actual);
    }

    /**
     * Канонический JSON: порядок ключей не важен, wound.heldBy вне diff.
     *
     * @param mixed $value Фрагмент листа.
     *
     * @return string Текст.
     *
     * @throws GameInvalidException Если значение не JSON.
     */
    private function canonical(mixed $value): string
    {
        try {
            return json_encode($this->normalize($value), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GameInvalidException('Game character snapshot is invalid', $exception);
        }
    }

    /**
     * Сортирует объекты и снимает heldBy внутри wound.
     *
     * @param mixed $value Фрагмент.
     *
     * @return mixed Нормализованное значение.
     */
    private function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if ($key === 'wound' && is_array($item)) {
                unset($item['heldBy']);
            }

            $normalized[$key] = $this->normalize($item);
        }

        if (!array_is_list($value)) {
            ksort($normalized);
        }

        return $normalized;
    }
}
