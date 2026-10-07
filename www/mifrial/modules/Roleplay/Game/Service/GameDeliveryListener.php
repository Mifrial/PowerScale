<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Service\IEventListener;
use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Event\Interface\Value\IEventPayload;
use Mifrial\Core\Event\Interface\Value\IEventResult;
use Mifrial\Roleplay\Game\Interface\Service\IGameDelivery;
use WeakMap;

/**
 * Пишет outbox по сигналу уже применённой команды.
 */
final class GameDeliveryListener implements IEventListener
{
    public const EVENT = 'Roleplay\\Game.Delivery::Recorded';

    /**
     * Уже подписанные менеджеры. Ключ — экземпляр порта, не флаг на все тесты.
     *
     * @var WeakMap<IEventManager, true>|null
     */
    private static ?WeakMap $registered = null;

    /**
     * Одна подписка на этот IEventManager.
     *
     * @param IEventManager $events Порт.
     * @param IGameDelivery $delivery Фасад.
     *
     * @return void
     */
    public static function register(IEventManager $events, IGameDelivery $delivery): void
    {
        self::$registered ??= new WeakMap();
        if (isset(self::$registered[$events])) {
            return;
        }

        self::$registered[$events] = true;
        $events->on(self::EVENT, new self($delivery));
    }

    /**
     * Создаёт слушателя.
     *
     * @param IGameDelivery $delivery Фасад.
     *
     * @return void
     */
    public function __construct(private readonly IGameDelivery $delivery)
    {
    }

    /**
     * Вставляет строку, если пары ещё нет.
     *
     * @param IEventPayload $payload Факт команды.
     *
     * @return IEventResult|null Успех.
     */
    public function handle(IEventPayload $payload): ?IEventResult
    {
        $keys = $payload->get('keys');
        $gameId = $payload->get('gameId');
        $sourceId = $payload->get('sourceId');
        $source = $payload->get('source');
        if (!is_int($gameId) || !is_string($source) || !is_int($sourceId) || !is_array($keys)) {
            return null;
        }

        $this->delivery->record($gameId, $source, $sourceId, $keys);

        return null;
    }
}
