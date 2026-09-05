<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

use Closure;
use Mifrial\Core\SmartTable\Exception\SmartTableException;
use Mifrial\Roleplay\Rule\Exception\RuleException;
use Mifrial\Roleplay\Rule\Exception\RuleNotFoundException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceNotFoundException;

/**
 * RULE_* и ST → RULESPACE_*.
 */
final class RuleSpaceGuard
{
    /**
     * Выполняет работу и мапит ошибки соседа.
     *
     * @param Closure $work Работа.
     *
     * @return mixed Результат.
     *
     * @throws RuleSpaceException Свои.
     * @throws RuleSpaceNotFoundException Нет мира/ревизии.
     * @throws RuleSpaceInvalidException Прочий отказ.
     */
    public function run(Closure $work): mixed
    {
        try {
            return $work();
        } catch (RuleSpaceException $exception) {
            throw $exception;
        } catch (RuleNotFoundException $exception) {
            throw new RuleSpaceNotFoundException($exception->getMessage(), $exception);
        } catch (RuleException | SmartTableException $exception) {
            throw new RuleSpaceInvalidException($exception->getMessage(), $exception);
        }
    }
}
