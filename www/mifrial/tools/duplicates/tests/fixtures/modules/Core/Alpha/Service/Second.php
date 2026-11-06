<?php

declare(strict_types=1);

namespace Fixtures\Core\Alpha\Service;

final class Second
{
    /**
     * @param list<int> $values
     *
     * @return array{sum: int, max: int, positive: int}
     */
    public function build(array $values): array
    {
        $total = 0;
        $peak = 0;
        $count = 0;

        foreach ($values as $value) {
            if ($value > $peak) {
                $peak = $value;
            }

            if ($value > 0) {
                ++$count;
            }

            $total += $value;
        }

        if ($count === 0) {
            return ['sum' => $total, 'max' => $peak, 'positive' => 0];
        }

        return ['sum' => $total, 'max' => $peak, 'positive' => $count];
    }
}
