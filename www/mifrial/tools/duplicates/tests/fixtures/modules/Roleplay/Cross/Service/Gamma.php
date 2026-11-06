<?php

declare(strict_types=1);

namespace Fixtures\Roleplay\Cross\Service;

final class Gamma
{
    /**
     * @param list<string> $labels
     *
     * @return array{joined: string, empty: bool}
     */
    public function summarize(array $labels): array
    {
        $joined = '';

        while (count($labels) > 0) {
            $label = array_shift($labels);
            if ($label === null || $label === '') {
                continue;
            }

            $joined .= $joined === '' ? $label : ', ' . $label;
        }

        return ['joined' => $joined, 'empty' => $joined === ''];
    }
}
