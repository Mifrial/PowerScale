<?php

declare(strict_types=1);

namespace Fixtures\Core\Alpha\Service;

final class Unique
{
    /**
     * @param list<array{key: string, score: float}> $items
     *
     * @return array<string, float>
     */
    public function rank(array $items): array
    {
        $ranked = [];

        foreach ($items as $item) {
            $key = $item['key'];
            $score = $item['score'];

            if (!isset($ranked[$key]) || $ranked[$key] < $score) {
                $ranked[$key] = $score;
            }
        }

        uksort($ranked, static fn(string $left, string $right): int => strcmp($left, $right));

        return $ranked;
    }
}
