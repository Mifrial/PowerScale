<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Преимущества объявленных поддержек строки удара.
 */
final class GameStrikeSupport
{
    /**
     * Поддержки, записанные в строку удара.
     *
     * @param array<string, mixed> $row Строка удара.
     *
     * @return array{attacker: list<RollAdvantage>, defender: list<RollAdvantage>} Преимущества.
     */
    public function advantages(array $row): array
    {
        $supports = ['attacker' => [], 'defender' => []];
        foreach ($this->decode($row['supporters'] ?? null) as $entry) {
            $support = $this->support($entry);
            if ($support === null) {
                continue;
            }

            [$side, $advantage] = $support;
            $supports[$side][] = $advantage;
        }

        return $supports;
    }

    /**
     * Записи поддержек из строки или JSON.
     *
     * @param mixed $raw Сырое значение.
     *
     * @return array<int, mixed> Записи.
     */
    private function decode(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) ? array_values($raw) : [];
    }

    /**
     * Сторона и преимущество одной записи.
     *
     * @param mixed $entry Запись.
     *
     * @return array{0: string, 1: RollAdvantage}|null Пара или null.
     */
    private function support(mixed $entry): ?array
    {
        $fields = $this->fields($entry);
        if ($fields === null) {
            return null;
        }

        return [
            $fields['side'],
            new RollAdvantage(
                $fields['source'],
                $fields['advantage'],
                AdvantageSourceLabels::getLabel($fields['source']),
            ),
        ];
    }

    /**
     * Проверенные поля одной записи.
     *
     * @param mixed $entry Запись.
     *
     * @return array{side: string, advantage: int, source: string}|null Поля или null.
     */
    private function fields(mixed $entry): ?array
    {
        if (!is_array($entry)) {
            return null;
        }

        $side = $entry['side'] ?? null;
        if ($side !== 'attacker' && $side !== 'defender') {
            return null;
        }

        return $this->sideFields($side, $entry);
    }

    /**
     * Число и источник записи с уже проверенной стороной.
     *
     * @param string $side Сторона.
     * @param array<string, mixed> $entry Запись.
     *
     * @return array{side: string, advantage: int, source: string}|null Поля или null.
     */
    private function sideFields(string $side, array $entry): ?array
    {
        $advantage = $entry['advantage'] ?? null;
        $source = $entry['source'] ?? null;
        if (!is_int($advantage) || $advantage === 0 || !is_string($source) || $source === '') {
            return null;
        }

        return ['side' => $side, 'advantage' => $advantage, 'source' => $source];
    }
}
