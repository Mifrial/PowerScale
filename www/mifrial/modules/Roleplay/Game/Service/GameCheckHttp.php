<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\AnswerGameCheckInput;
use Mifrial\Roleplay\Game\Dto\Action\CancelGameCheckInput;
use Mifrial\Roleplay\Game\Dto\Action\DeclareGameCheckInput;
use Mifrial\Roleplay\Game\Dto\Action\GetGameCheckOffersInput;
use Mifrial\Roleplay\Game\Dto\Action\ProposeGameCheckInput;
use Mifrial\Roleplay\Game\Dto\Action\ReviseGameCheckInput;
use Mifrial\Roleplay\Game\Interface\Service\IGameChecks;

/**
 * HTTP проверки. В handle action только этот сценарий.
 */
final class GameCheckHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGameChecks $checks Фасад.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGameChecks $checks,
    ) {
    }

    /**
     * Соло.
     *
     * @param DeclareGameCheckInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function declareCheck(DeclareGameCheckInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->checks->declareCheck(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->battleId,
            $input->idempotencyKey,
            $input->expectedSheetVersion,
            $input->check,
        );
    }

    /**
     * Предложение.
     *
     * @param ProposeGameCheckInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function proposeCheck(ProposeGameCheckInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->checks->proposeCheck(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->battleId,
            $input->idempotencyKey,
            $input->expectedSheetVersion,
            $input->proposal,
        );
    }

    /**
     * Ответ.
     *
     * @param AnswerGameCheckInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function answerCheck(AnswerGameCheckInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->checks->answerCheck(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->processId,
            $input->idempotencyKey,
            $input->expectedSheetVersion,
            $input->answer,
        );
    }

    /**
     * Правка предложения.
     *
     * @param ReviseGameCheckInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function reviseCheck(ReviseGameCheckInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->checks->reviseCheck(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->processId,
            $input->idempotencyKey,
            $input->expectedSheetVersion,
            $input->actor === [] ? $input->proposal : [...$input->proposal, 'actor' => $input->actor],
        );
    }

    /**
     * Отмена предложения.
     *
     * @param CancelGameCheckInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function cancelCheck(CancelGameCheckInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->checks->cancelCheck(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->processId,
            $input->idempotencyKey,
            $input->proposal,
        );
    }

    /**
     * Ожидающие предложения.
     *
     * @param GetGameCheckOffersInput $input JSON.
     *
     * @return array<int, array<string, mixed>> Предложения.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function checkOffers(GetGameCheckOffersInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->checks->checkOffers(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->entity,
        );
    }
}
