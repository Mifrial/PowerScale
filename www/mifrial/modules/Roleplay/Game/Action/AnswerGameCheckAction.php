<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\AnswerGameCheckInput;
use Mifrial\Roleplay\Game\Service\GameCheckHttp;

/**
 * Ответ на предложение проверки.
 */
final class AnswerGameCheckAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameCheckHttp $checkHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCheckHttp $checkHttp,
    ) {
    }

    /**
     * Принимает или отклоняет.
     *
     * @param AnswerGameCheckInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(AnswerGameCheckInput $input): array
    {
        return $this->checkHttp->answerCheck($input);
    }
}
