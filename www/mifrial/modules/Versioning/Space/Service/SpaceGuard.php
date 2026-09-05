<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Service;

use Closure;
use Mifrial\Core\Cache\Exception\CacheException;
use Mifrial\Core\SmartTable\Exception\SmartTableException;
use Mifrial\Versioning\Space\Exception\SpaceException;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;

/**
 * Оборачивает ST и Cache в SPACE_INVALID.
 */
final class SpaceGuard
{
    /**
     * Выполняет работу и переводит чужие коды модуля.
     *
     * @param Closure $work Работа репозитория.
     *
     * @return mixed Результат работы.
     *
     * @throws SpaceException Свои ошибки без обёртки.
     * @throws SpaceInvalidException Если ST или Cache отказали.
     */
    public function run(Closure $work): mixed
    {
        try {
            return $work();
        } catch (SpaceException $exception) {
            throw $exception;
        } catch (SmartTableException $exception) {
            throw new SpaceInvalidException($exception->getMessage(), $exception);
        } catch (CacheException $exception) {
            throw new SpaceInvalidException($exception->getMessage(), $exception);
        }
    }
}
