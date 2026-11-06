<?php

declare(strict_types=1);

namespace Fixtures\Core\Alpha\Service;

final class Allowed
{
    /**
     * @param array<string, int> $weights
     *
     * @return list<string>
     */
    public function collect(array $weights): array
    {
        $result = [];

        foreach ($weights as $name => $weight) {
            if ($weight <= 0) {
                continue;
            }

            $result[] = $name;
        }

        return $result;
    }
    /**
     * @param list<string> $names
     *
     * @return array<string, int>
     */
    public function countByName(array $names): array
    {
        $counts = [];

        foreach ($names as $name) {
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        return $counts;
    }
}
