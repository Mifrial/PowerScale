<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Interface\Http;

/**
 * Кладёт снимок актора в контекст запроса. Реализация — модуль сессии.
 */
interface IRequestBinder
{
    /**
     * Заполняет актора по входящим cookie контекста.
     *
     * @param IRequestContext $requestContext Контекст процесса.
     *
     * @return void
     */
    public function bind(IRequestContext $requestContext): void;
}
