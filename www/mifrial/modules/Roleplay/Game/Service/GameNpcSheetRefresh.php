<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheetEngines;

/**
 * Свежий лист NPC из сохранённых choices.
 *
 * `npc.version.sheet` — последний сохранённый снимок; чтение пересчитывает
 * его на ревизии NPC, чтобы карточка, редактор и каст видели одно и то же.
 */
final class GameNpcSheetRefresh
{
    /**
     * Создаёт пересчёт.
     *
     * @param ICharacterSheetEngines $sheetEngines Сборка листа.
     *
     * @return void
     */
    public function __construct(private readonly ICharacterSheetEngines $sheetEngines)
    {
    }

    /**
     * Возвращает версию со свежим листом, если сборка прошла.
     *
     * @param array<string, mixed> $version Версия NPC.
     *
     * @return array<string, mixed> Версия.
     */
    public function apply(array $version): array
    {
        $choices = $version['choices'] ?? null;
        $spaceId = $version['spaceId'] ?? null;
        $revision = $version['rulesRevision'] ?? null;
        $sheet = $version['sheet'] ?? null;
        if (!is_array($choices) || !is_int($spaceId) || !is_int($revision) || !is_array($sheet)) {
            return $version;
        }

        try {
            $report = $this->sheetEngines->build($choices, $spaceId, $revision, null, $sheet);
        } catch (\Throwable) {
            return $version;
        }

        if (($report['kind'] ?? null) !== 'ok' || !is_array($report['sheet'] ?? null)) {
            return $version;
        }

        $refreshed = self::mergeRuntime($report['sheet'], $sheet);

        $version['sheet'] = $refreshed;

        return $version;
    }

    /**
     * Возвращает лист с runtime-ключами предыдущего снимка.
     *
     * @param array<string, mixed> $sheet Пересобранный лист.
     * @param array<string, mixed>|null $previousSheet Предыдущий лист или null.
     *
     * @return array<string, mixed> Лист.
     */
    public static function mergeRuntime(array $sheet, ?array $previousSheet): array
    {
        if ($previousSheet === null) {
            return $sheet;
        }

        if (array_key_exists('states', $previousSheet) && !array_key_exists('states', $sheet)) {
            $sheet['states'] = self::storedStates($previousSheet['states']);
        }

        return $sheet;
    }

    /**
     * Сохранённые состояния без временных строк эффектов.
     *
     * @param mixed $states Состояния предыдущего снимка.
     *
     * @return array<int, mixed> Состояния.
     */
    private static function storedStates(mixed $states): array
    {
        if (!is_array($states)) {
            return [];
        }

        return array_values(array_filter(
            $states,
            static fn (mixed $row): bool => !is_array($row) || ($row['runtime'] ?? false) !== true,
        ));
    }
}
