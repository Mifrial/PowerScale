<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Чёрный список секций строки персонажа. Публичный лист Character не вызывает.
 */
final class GameCharacterProjectionMask
{
    /**
     * @var array<string, array<int, string>>
     */
    private const HIDDEN = [
        'characteristics' => [
            'choices.characteristicPurchases',
            'sheet.characteristicPurchases',
            'sheet.characteristicPurchaseOs',
            'sheet.osSurchargeTotal',
        ],
        'abilities' => [
            'choices.abilities',
            'sheet.abilityLevels',
            'sheet.coveredPaths',
        ],
        'inventory' => [
            'choices.inventory',
            'sheet.equippedModifiers',
        ],
        'resources' => [
            'choices.money',
            'sheet.money',
        ],
        'shortDescription' => ['choices.shortDescription'],
        'fullDescription' => ['choices.fullDescription'],
        'race' => ['choices.raceCode', 'sheet.racialAbilityCodes'],
    ];

    /**
     * Снимает закрытые секции. Полный лист не трогает.
     *
     * @param array<string, mixed> $choices Документ.
     * @param array<string, mixed> $sheet Снимок.
     * @param array<int, string> $openSections Коды, которые видны.
     * @param bool $fullSheet Владелец строки, владелец игры или gm.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Документы.
     */
    public function apply(array $choices, array $sheet, array $openSections, bool $fullSheet): array
    {
        if ($fullSheet) {
            return ['choices' => $choices, 'sheet' => $sheet];
        }

        $version = ['choices' => $choices, 'sheet' => $sheet];
        foreach (self::HIDDEN as $section => $paths) {
            if (in_array($section, $openSections, true)) {
                continue;
            }

            $version = $this->hide($version, $paths);
        }

        $maskedChoices = is_array($version['choices'] ?? null) ? $version['choices'] : [];
        $maskedSheet = is_array($version['sheet'] ?? null) ? $version['sheet'] : [];

        return ['choices' => $maskedChoices, 'sheet' => $maskedSheet];
    }

    /**
     * Снимает пути choices.* и sheet.*.
     *
     * @param array<string, mixed> $version Лист.
     * @param array<int, string> $paths Пути.
     *
     * @return array<string, mixed> Лист.
     */
    private function hide(array $version, array $paths): array
    {
        foreach ($paths as $path) {
            [$bag, $key] = explode('.', $path, 2);
            if (!is_array($version[$bag] ?? null)) {
                continue;
            }

            unset($version[$bag][$key]);
        }

        return $version;
    }
}
