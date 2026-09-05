<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Keyword\Dto\Action\CreateKeywordInput;
use Mifrial\Roleplay\Keyword\Service\KeywordHttpService;

/**
 * Создание признака.
 */
final class CreateKeywordAction implements IActionHandler
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
     * @param CreateKeywordInput $input JSON.
     *
     * @return array<string, mixed> Признак.
     */
    public function handle(CreateKeywordInput $input): array
    {
        return $this->keywordHttpService->create($input);
    }
}
