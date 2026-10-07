<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Mechanic\Interface\MechanicPayload;

/**
 * Бросок без правки успеха. Нужен, когда каталог ссылается на поставку, а подписок нет.
 */
final class PlainRollHandler implements IMechanicHandler
{
    /**
     * Код семейства.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return 'plain_roll';
    }

    /**
     * Поставка.
     *
     * @return string Версия.
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Подписок нет.
     *
     * @return array<string, int> Пустая карта.
     */
    public function getSubscriptions(): array
    {
        return [];
    }

    /**
     * Ничего не меняет.
     *
     * @param MechanicPayload|null $payload Payload.
     * @param object $context Контекст.
     * @param string $event Событие.
     *
     * @return void
     */
    public function run(?MechanicPayload $payload, object $context, string $event): void // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInImplementedInterfaceBeforeLastUsed
    {
    }
}
