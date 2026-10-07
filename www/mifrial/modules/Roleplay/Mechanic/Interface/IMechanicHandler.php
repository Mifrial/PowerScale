<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Interface;

/**
 * Хендлер механики: подписки события на приоритет и мутация контекста.
 */
interface IMechanicHandler
{
    /**
     * Код семейства.
     *
     * @return string Код.
     */
    public function getCode(): string;

    /**
     * Поставка контракта.
     *
     * @return string Версия.
     */
    public function getVersion(): string;

    /**
     * Подписки: имя события → приоритет. Меньше — раньше.
     *
     * @return array<string, int> Карта.
     */
    public function getSubscriptions(): array;

    /**
     * Выполняет механику на контексте события.
     *
     * @param MechanicPayload|null $payload Payload binding.
     * @param object $context Контекст шага; хендлер сам проверяет тип.
     * @param string $event Имя события.
     *
     * @return void
     */
    public function run(?MechanicPayload $payload, object $context, string $event): void;
}
