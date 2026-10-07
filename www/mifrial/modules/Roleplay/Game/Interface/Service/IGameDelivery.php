<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Interface\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Доставка уже применённой команды. Лист не пишет.
 */
interface IGameDelivery
{
    /**
     * Кладёт строку, если пары ещё нет.
     *
     * @param int $gameId Игра.
     * @param string $source Источник economy или strike.
     * @param int $sourceId Id журнала.
     * @param array<int, array<string, mixed>> $keys Ключи листов.
     *
     * @return void
     *
     * @throws GameInvalidException Если поле.
     */
    public function record(int $gameId, string $source, int $sourceId, array $keys): void;

    /**
     * Дописывает строки из журналов, не повторяя мутацию.
     *
     * @param int $gameId Игра.
     *
     * @return void
     *
     * @throws GameInvalidException Если итог битый.
     */
    public function catchUp(int $gameId): void;

    /**
     * Хвост для актора. Курсор — id последней учтённой строки.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param int|null $lastCursor Нижняя граница. null — пустой hello.
     *
     * @return array{cursor: int, events: array<int, array<string, mixed>>} Кадр.
     *
     * @throws GameNotFoundException Если карточка скрыта.
     */
    public function tail(int $gameId, int $actorUserId, bool $viewAll, ?int $lastCursor): array;
}
