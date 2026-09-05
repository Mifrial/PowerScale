<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Keyword\Dto\Action\UpdateKeywordInput;
use Mifrial\Roleplay\Keyword\Service\KeywordHttpService;

/**
 * Обновление признака.
 */
final class UpdateKeywordAction implements IActionHandler
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
     * @param UpdateKeywordInput $input JSON.
     *
     * @return array<string, mixed> Признак.
     */
    public function handle(UpdateKeywordInput $input): array
    {
        return $this->keywordHttpService->update($input);
    }
}
