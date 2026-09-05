<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Keyword\Service\KeywordHttpService;

/**
 * Каталог признаков.
 */
final class GetKeywordListAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param KeywordHttpService $keywordHttpService Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly KeywordHttpService $keywordHttpService,
    ) {
    }

    /**
     * Возвращает Keyword[].
     *
     * @return array<int, array<string, mixed>> Признаки.
     */
    public function handle(): array
    {
        return $this->keywordHttpService->getList();
    }
}
