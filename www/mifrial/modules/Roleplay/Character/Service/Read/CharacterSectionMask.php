<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Read;

/**
 * Оставляет в choices и sheet ключи выданных секций.
 */
final class CharacterSectionMask
{
    /**
     * @var array<string, list<string>>
     */
    private const CHOICES = [
        'shortDescription' => ['shortDescription'],
        'fullDescription' => ['fullDescription'],
        'race' => ['raceCode'],
        'characteristics' => ['characteristicPurchases'],
        'resources' => ['money'],
        'abilities' => ['abilities'],
        'inventory' => ['inventory'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const SHEET = [
        'race' => ['racialAbilityCodes'],
        'characteristics' => ['characteristicPurchases', 'characteristicPurchaseOs', 'osSurchargeTotal'],
        'resources' => ['money'],
        'abilities' => ['abilityLevels', 'coveredPaths'],
        'inventory' => ['equippedModifiers'],
    ];

    /**
     * Режет документы. Не решает, виден ли лист.
     *
     * @param array<string, mixed> $choices Choices.
     * @param array<string, mixed> $sheet Sheet.
     * @param array<int, string> $sections Выданные коды.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Документы.
     */
    public function apply(array $choices, array $sheet, array $sections): array
    {
        $maskedSheet = $this->pick($sheet, $sections, self::SHEET);
        if (array_key_exists('active', $sheet)) {
            $maskedSheet['active'] = $sheet['active'];
        }

        return [
            'choices' => $this->pick($choices, $sections, self::CHOICES),
            'sheet' => $maskedSheet,
        ];
    }

    /**
     * Копирует существующие ключи секций.
     *
     * @param array<string, mixed> $document Документ.
     * @param array<int, string> $sections Коды.
     * @param array<string, list<string>> $keysBySection Карта.
     *
     * @return array<string, mixed> Срез.
     */
    private function pick(array $document, array $sections, array $keysBySection): array
    {
        $picked = [];
        foreach ($sections as $section) {
            foreach ($keysBySection[$section] ?? [] as $key) {
                if (array_key_exists($key, $document)) {
                    $picked[$key] = $document[$key];
                }
            }
        }

        return $picked;
    }
}
