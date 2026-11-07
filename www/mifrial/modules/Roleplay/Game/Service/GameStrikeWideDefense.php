<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Разбор защиты по нескольким целям.
 */
final class GameStrikeWideDefense
{
    /**
     * Создаёт разбор широкой защиты.
     *
     * @param GameStrikeBodyFields $fields Проверки формы.
     * @param GameStrikeReaction $reaction Строка реакции.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeBodyFields $fields,
        private readonly GameStrikeReaction $reaction,
    ) {
    }

    /**
     * Выбор защиты по нескольким целям.
     *
     * @param array<string, mixed> $defense Тело.
     *
     * @return list<array<string, mixed>> Защиты.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(array $defense): array
    {
        $this->fields->assertKeys($defense, ['defenses'], 'wide strike');
        $rows = $defense['defenses'] ?? null;
        if (!is_array($rows) || $rows === [] || !array_is_list($rows)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense');
        }

        $choices = [];
        $seen = [];
        foreach ($rows as $row) {
            $choice = $this->row($row);
            $key = $choice['target']['type'] . ':' . $choice['target']['id'];
            if (isset($seen[$key])) {
                throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense target');
            }

            $seen[$key] = true;
            $choices[] = $choice;
        }

        return $choices;
    }

    /**
     * Одна защита широкого удара.
     *
     * @param mixed $row Элемент.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function row(mixed $row): array
    {
        if (!is_array($row)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense row');
        }

        return $this->reaction->choice(
            $row,
            ['expectedSheetVersion', 'reaction', 'target'],
            true,
            'Invalid parameter: defense values ' . json_encode($row),
        );
    }
}
