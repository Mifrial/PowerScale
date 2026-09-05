<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Service;

use Closure;
use Mifrial\Core\SmartTable\Exception\SmartTableException;
use Mifrial\Roleplay\Rule\Exception\RuleException;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Exception\RuleNotFoundException;
use Mifrial\Versioning\Space\Exception\SpaceException;
use Mifrial\Versioning\Space\Exception\SpaceNotFoundException;

/**
 * Часы и ST identity → RULE_*.
 */
final class RuleGuard
{
    /**
     * Выполняет работу и мапит SPACE_* / ST.
     *
     * @param Closure $work Работа.
     *
     * @return mixed Результат.
     *
     * @throws RuleException Свои.
     * @throws RuleNotFoundException Нет строки часов.
     * @throws RuleInvalidException Прочий отказ часов или ST.
     */
    public function run(Closure $work): mixed
    {
        try {
            return $work();
        } catch (RuleException $exception) {
            throw $exception;
        } catch (SpaceNotFoundException $exception) {
            throw new RuleNotFoundException($exception->getMessage(), $exception);
        } catch (SpaceException | SmartTableException $exception) {
            throw new RuleInvalidException($exception->getMessage(), $exception);
        }
    }
}
