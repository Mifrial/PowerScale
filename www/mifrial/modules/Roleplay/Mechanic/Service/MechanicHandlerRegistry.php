<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service;

use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;

/**
 * Реестр хендлеров: ключ code@version. Повторная регистрация перезаписывает ключ.
 */
final class MechanicHandlerRegistry
{
    /**
     * Хендлеры по ключу поставки.
     *
     * @var array<string, IMechanicHandler>
     */
    private array $byKey = [];

    /**
     * Кладёт хендлер под code@version.
     *
     * @param IMechanicHandler $handler Хендлер.
     *
     * @return void
     */
    public function register(IMechanicHandler $handler): void
    {
        $this->byKey[$handler->getCode() . '@' . $handler->getVersion()] = $handler;
    }

    /**
     * Ищет хендлер поставки.
     *
     * @param string $code Код семейства.
     * @param string $version Поставка.
     *
     * @return IMechanicHandler|null Хендлер или null, если ключа нет.
     */
    public function resolve(string $code, string $version): ?IMechanicHandler
    {
        return $this->byKey[$code . '@' . $version] ?? null;
    }
}
