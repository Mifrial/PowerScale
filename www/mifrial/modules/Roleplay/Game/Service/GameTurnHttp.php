<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\GameTurnInput;

/**
 * HTTP конца хода.
 */
final class GameTurnHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param GameTurns $turns Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly GameTurns $turns,
    ) {
    }

    /**
     * Конец хода текущего.
     *
     * @param GameTurnInput $input Вход.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function end(GameTurnInput $input): array
    {
        return $this->call($input, 'end');
    }

    /**
     * Доплата долга текущего.
     *
     * @param GameTurnInput $input Вход.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function continueAction(GameTurnInput $input): array
    {
        return $this->call($input, 'continue');
    }

    /**
     * Снимает долг текущего.
     *
     * @param GameTurnInput $input Вход.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function abort(GameTurnInput $input): array
    {
        return $this->call($input, 'abort');
    }

    /**
     * Прерывает процесс текущего.
     *
     * @param GameTurnInput $input Вход.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function abortProcess(GameTurnInput $input): array
    {
        return $this->call($input, 'abort_process');
    }

    /**
     * Выбирает команду хода.
     *
     * @param GameTurnInput $input Вход.
     * @param string $command Команда.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    private function call(GameTurnInput $input, string $command): array
    {
        $actor = $this->userAccess->requireActor();
        $userId = $actor->getUserId();
        $editAll = $actor->hasKey(GamePermissionKeys::EDIT_ALL);
        $viewAll = $actor->hasKey(GamePermissionKeys::VIEW_ALL);
        $body = $input->body();

        return match ($command) {
            'continue' => $this->turns->continueAction($input->gameId, $userId, $editAll, $viewAll, $body),
            'abort' => $this->turns->abort($input->gameId, $userId, $editAll, $viewAll, $body),
            'abort_process' => $this->turns->abortProcess($input->gameId, $userId, $editAll, $viewAll, $body),
            default => $this->turns->end($input->gameId, $userId, $editAll, $viewAll, $body),
        };
    }
}
