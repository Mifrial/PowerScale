<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход чтения среза ревизии.
 */
final class GetRevisionInput implements IActionInput
{
    /**
     * Собирает вход среза.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер.
     *
     * @return void
     */
    public function __construct(
        public readonly int $spaceId,
        public readonly int $revision,
    ) {
    }
}
