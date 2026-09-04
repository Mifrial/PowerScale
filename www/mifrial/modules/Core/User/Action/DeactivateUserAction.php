<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\User\Dto\Action\DeactivateUserInput;
use Mifrial\Core\User\Service\UserHttpService;

/**
 * Деактивация учётки.
 */
final class DeactivateUserAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param UserHttpService $userHttpService Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly UserHttpService $userHttpService,
    ) {
    }

    /**
     * Снимает active.
     *
     * @param DeactivateUserInput $input JSON deactivate.
     *
     * @return null Успех.
     */
    public function handle(DeactivateUserInput $input): mixed
    {
        return $this->userHttpService->deactivate($input);
    }
}
