<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Cache;

use Closure;

/**
 * Теги кэша детей после SQL CASCADE/SET NULL.
 */
final class DependentRowCacheTags
{
    /**
     * Собирает теги стола и get-строк зависимых таблиц.
     *
     * @param Closure|null $dependentTableNames Имена или null.
     * @param bool $storeOpen Можно ли сбрасывать store.
     *
     * @return array<int, string> Теги.
     */
    public static function names(?Closure $dependentTableNames, bool $storeOpen): array
    {
        if (!$storeOpen || !$dependentTableNames instanceof Closure) {
            return [];
        }

        $tagNames = [];
        foreach ($dependentTableNames() as $dependentName) {
            if (!is_string($dependentName) || $dependentName === '') {
                continue;
            }

            $tagNames[] = 'st:' . $dependentName;
            $tagNames[] = 'st:' . $dependentName . ':rows';
        }

        return $tagNames;
    }
}
