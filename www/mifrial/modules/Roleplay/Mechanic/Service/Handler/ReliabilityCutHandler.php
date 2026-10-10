<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Mechanic\Interface\IReliabilityCut;
use Mifrial\Roleplay\Mechanic\Interface\MechanicPayload;

/**
 * Маркер механики среза надёжности без событийных побочных эффектов.
 */
final class ReliabilityCutHandler implements IMechanicHandler, IReliabilityCut
{
    /**
     * Код семейства.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return 'reliability_cut';
    }

    /**
     * Поставка контракта.
     *
     * @return string Версия.
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Подписок на события нет.
     *
     * @return array<string, int> Пустая карта.
     */
    public function getSubscriptions(): array
    {
        return [];
    }

    /**
     * Ничего не меняет в контексте события.
     *
     * @param MechanicPayload|null $payload Канонический пустой payload.
     * @param object $context Контекст события.
     * @param string $event Имя события.
     *
     * @return void
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInImplementedInterfaceBeforeLastUsed
    public function run(
        ?MechanicPayload $payload,
        object $context,
        string $event,
    ): void {
    }
}
