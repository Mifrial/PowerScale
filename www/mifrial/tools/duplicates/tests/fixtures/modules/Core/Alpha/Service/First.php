<?php

declare(strict_types=1);

namespace Fixtures\Core\Alpha\Service;

final class First
{
    /**
     * @param list<int> $values
     *
     * @return array{sum: int, max: int, positive: int}
     */
    public function build(array $values): array
    {
        $sum = 0;
        $max = 0;
        $positive = 0;

        foreach ($values as $value) {
            if ($value > $max) {
                $max = $value;
            }

            if ($value > 0) {
                ++$positive;
            }

            $sum += $value;
        }

        if ($positive === 0) {
            return ['sum' => $sum, 'max' => $max, 'positive' => 0];
        }

        return ['sum' => $sum, 'max' => $max, 'positive' => $positive];
    }
}
