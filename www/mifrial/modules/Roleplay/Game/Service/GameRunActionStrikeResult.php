<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Нормализация итога объявления удара.
 */
final class GameRunActionStrikeResult
{
    /**
     * Собирает ответ объявления.
     *
     * @param array<string, mixed> $result Итог домена.
     * @param list<array<string, mixed>> $strikes Запрошенные строки.
     *
     * @return array<string, mixed> Итог.
     */
    public function build(array $result, array $strikes): array
    {
        $response = [
            'kind' => 'strike',
            'phase' => 'declare',
            'battleId' => $result['battleId'] ?? 0,
            'version' => $result['version'] ?? 0,
            'strikeId' => $result['strikeId'] ?? 0,
            'spent' => $result['spent'] ?? 0,
            'closed' => false,
            'sheetVersion' => null,
            'strikes' => $this->rows($strikes),
            'pendingStrikes' => $this->pending($strikes),
        ];
        if (($result['debt'] ?? false) === true) {
            $response['debt'] = true;
        }

        return $response;
    }

    /**
     * Строки объявленного удара.
     *
     * @param list<array<string, mixed>> $strikes Запрошенные строки.
     *
     * @return list<array<string, mixed>> Строки.
     */
    private function rows(array $strikes): array
    {
        $rows = [];
        foreach ($strikes as $index => $strike) {
            $rows[] = [
                'index' => $index,
                'itemInventoryId' => $strike['itemInventoryId'],
                'itemRuleCode' => $strike['itemRuleCode'],
                'profileType' => $strike['profileType'],
                'profileIndex' => $strike['profileIndex'],
                'instanceIndex' => $strike['instanceIndex'],
                'target' => $strike['target'],
                'reaction' => null,
                'conditions' => $strike['conditions'] ?? (new GameStrikeConditions())->defaults(),
            ];
        }

        return $rows;
    }

    /**
     * Ожидающие ответа строки.
     *
     * @param list<array<string, mixed>> $strikes Запрошенные строки.
     *
     * @return list<array<string, mixed>> Строки.
     */
    private function pending(array $strikes): array
    {
        $pending = [];
        foreach ($strikes as $index => $strike) {
            $pending[] = ['index' => $index, 'target' => $strike['target']];
        }

        return $pending;
    }
}
