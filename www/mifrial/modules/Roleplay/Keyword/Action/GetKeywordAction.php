<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Keyword\Service\KeywordHttpService;

/**
 * Признак по id.
 */
final class GetKeywordAction implements IActionHandler
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
     * Возвращает Keyword.
     *
     * @param int $id Keyword id.
     *
     * @return array<string, mixed> Признак.
     */
    public function handle(int $id): array
    {
        return $this->keywordHttpService->get($id);
    }
}
