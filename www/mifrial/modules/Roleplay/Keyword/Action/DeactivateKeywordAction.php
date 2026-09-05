<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Keyword\Service\KeywordHttpService;

/**
 * Выключение признака.
 */
final class DeactivateKeywordAction implements IActionHandler
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
     * Возвращает null.
     *
     * @param int $id Keyword id.
     *
     * @return null Успех.
     */
    public function handle(int $id): mixed
    {
        return $this->keywordHttpService->deactivate($id);
    }
}
