<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Service;

use Mifrial\Core\Kernel\Http\SseEmitter;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Messages\Chat\Dto\ChatSseQuery;
use Mifrial\Messages\Chat\Dto\MessageRecord;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Messages\Chat\Interface\Service\ISseClock;
use Mifrial\Messages\Chat\Repository\ChatMessageRepository;
use stdClass;

/**
 * Цикл SSE чата: актор, дельта id, backoff. Не inbox dump.
 */
final class ChatSseService
{
    private const MESSAGE_LIMIT = 500;

    private const SLEEP_START = 2;

    private const SLEEP_CAP = 5;

    private const HEARTBEAT_SECONDS = 60;

    private int $userId = 0;

    /**
     * @var array<int, int>
     */
    private array $classifiedChatIds = [];

    /**
     * @var array<int, int>
     */
    private array $knownChatIds = [];

    private int $sinceUnix = 0;

    private int $afterId = 0;

    private int $backoffSeconds = self::SLEEP_START;

    private int $lastWriteUnix = 0;

    /**
     * Создаёт сценарий потока.
     *
     * @param IUserAccess $userAccess Guard.
     * @param IChats $chats Id членства.
     * @param ChatViewAssembler $viewAssembler JSON чатов.
     * @param ChatMessageRepository $messageRepository Keyset сообщений.
     * @param IUserAccounts $userAccounts Имена авторов.
     * @param ISseClock $sseClock Горизонт и sleep.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IChats $chats,
        private readonly ChatViewAssembler $viewAssembler,
        private readonly ChatMessageRepository $messageRepository,
        private readonly IUserAccounts $userAccounts,
        private readonly ISseClock $sseClock,
    ) {
    }

    /**
     * Держит поток, пока клиент не закрыл соединение.
     *
     * @param IHttpRequest $httpRequest Query курсора.
     * @param SseEmitter $sseEmitter Байты.
     *
     * @return void
     */
    public function run(IHttpRequest $httpRequest, SseEmitter $sseEmitter): void
    {
        $this->openStream($httpRequest, $sseEmitter);
        while (!$this->sseClock->isAborted()) {
            $this->finishTick($sseEmitter, $this->tick($sseEmitter));
        }
    }

    /**
     * Актор, hello или курсор reconnect, старт байтов.
     *
     * @param IHttpRequest $httpRequest Query.
     * @param SseEmitter $sseEmitter Поток.
     *
     * @return void
     */
    private function openStream(IHttpRequest $httpRequest, SseEmitter $sseEmitter): void
    {
        $this->userId = $this->userAccess->requireActor()->getUserId();
        $query = ChatSseQuery::fromRequest($httpRequest);
        $membershipIds = $this->chats->getChatIdsOfUser($this->userId);
        $this->classifiedChatIds = $membershipIds;
        $this->knownChatIds = $this->viewAssembler->hostChatIds($membershipIds);
        $sseEmitter->start();
        $this->lastWriteUnix = $this->sseClock->now()->toUnix();
        if ($query->isLive) {
            $this->emitHello($sseEmitter);

            return;
        }

        $this->sinceUnix = $query->sinceUnix;
        $this->afterId = $query->afterId;
    }

    /**
     * Живой вход: кадр с now, без ленты.
     *
     * @param SseEmitter $sseEmitter Поток.
     *
     * @return void
     */
    private function emitHello(SseEmitter $sseEmitter): void
    {
        $horizon = $this->sseClock->now();
        $this->sinceUnix = $horizon->toUnix();
        $this->afterId = 0;
        $this->writeFrame($sseEmitter, $this->emptyFrame($this->sinceUnix, 0));
    }

    /**
     * Выборка дельты; true если ушёл JSON-кадр.
     *
     * @param SseEmitter $sseEmitter Поток.
     *
     * @return bool Была дельта.
     */
    private function tick(SseEmitter $sseEmitter): bool
    {
        $horizon = $this->sseClock->now();
        $newChatIds = $this->refreshHostMemberships();
        $messageRecords = $this->messageRepository->getUpdatedSince(
            $this->knownChatIds,
            $this->userId,
            $this->sinceUnix,
            $this->afterId,
            $horizon,
            self::MESSAGE_LIMIT,
        );
        $frame = $this->deltaFrame($newChatIds, $messageRecords);
        if ($frame === null) {
            return false;
        }

        $this->writeFrame($sseEmitter, $frame);
        $this->advanceCursor($messageRecords);
        $this->backoffSeconds = self::SLEEP_START;

        return true;
    }

    /**
     * Sleep, backoff пустого тика, heartbeat.
     *
     * @param SseEmitter $sseEmitter Поток.
     * @param bool $emitted Был JSON-кадр.
     *
     * @return void
     */
    private function finishTick(SseEmitter $sseEmitter, bool $emitted): void
    {
        $this->sseClock->sleep($this->backoffSeconds);
        if (!$emitted) {
            $this->backoffSeconds = min(self::SLEEP_CAP, $this->backoffSeconds * 2);
        }

        $this->writeHeartbeatIfDue($sseEmitter);
    }

    /**
     * Новые host-id; known только host и текущее членство.
     *
     * @return array<int, int> Новые host-id.
     */
    private function refreshHostMemberships(): array
    {
        $membershipIds = $this->chats->getChatIdsOfUser($this->userId);
        $unclassifiedIds = array_values(array_diff($membershipIds, $this->classifiedChatIds));
        $this->classifiedChatIds = $membershipIds;
        $newHostIds = $this->viewAssembler->hostChatIds($unclassifiedIds);
        $stillMember = array_values(array_intersect($this->knownChatIds, $membershipIds));
        $this->knownChatIds = array_values(array_unique(array_merge($stillMember, $newHostIds)));

        return $newHostIds;
    }

    /**
     * Кадр дельты или null, если нечего слать.
     *
     * @param array<int, int> $newChatIds Новые id членства.
     * @param array<int, MessageRecord> $messageRecords Сообщения тика.
     *
     * @return array<string, mixed>|null JSON-кадр.
     */
    private function deltaFrame(array $newChatIds, array $messageRecords): ?array
    {
        if ($newChatIds === [] && $messageRecords === []) {
            return null;
        }

        $messageChatIds = $this->chatIdsOf($messageRecords);
        $knownWithMessages = array_values(array_diff($messageChatIds, $newChatIds));
        $cursor = $this->cursorOf($messageRecords);

        return [
            'now' => $cursor['now'],
            'afterId' => $cursor['afterId'],
            'chats' => $this->viewAssembler->assembleChatsByIds($this->userId, $knownWithMessages),
            'newChats' => $this->viewAssembler->assembleChatsByIds($this->userId, $newChatIds),
            'messages' => $this->messagesMap($messageRecords),
        ];
    }

    /**
     * Пустой hello-кадр.
     *
     * @param int $nowUnix Стена hello.
     * @param int $afterId 0.
     *
     * @return array<string, mixed> Кадр.
     */
    private function emptyFrame(int $nowUnix, int $afterId): array
    {
        return [
            'now' => $nowUnix,
            'afterId' => $afterId,
            'chats' => [],
            'newChats' => [],
            'messages' => new stdClass(),
        ];
    }

    /**
     * Курсор кадра: последняя строка или текущая пара процесса.
     *
     * @param array<int, MessageRecord> $messageRecords Строки.
     *
     * @return array{now: int, afterId: int} Пара.
     */
    private function cursorOf(array $messageRecords): array
    {
        if ($messageRecords === []) {
            return ['now' => $this->sinceUnix, 'afterId' => $this->afterId];
        }

        $lastRecord = $messageRecords[array_key_last($messageRecords)];

        return [
            'now' => $lastRecord->getUpdatedAt()->toUnix(),
            'afterId' => $lastRecord->getId(),
        ];
    }

    /**
     * Двигает курсор процесса по последней строке.
     *
     * @param array<int, MessageRecord> $messageRecords Строки кадра.
     *
     * @return void
     */
    private function advanceCursor(array $messageRecords): void
    {
        if ($messageRecords === []) {
            return;
        }

        $lastRecord = $messageRecords[array_key_last($messageRecords)];
        $this->sinceUnix = $lastRecord->getUpdatedAt()->toUnix();
        $this->afterId = $lastRecord->getId();
    }

    /**
     * event: sync и метка heartbeat.
     *
     * @param SseEmitter $sseEmitter Поток.
     * @param array<string, mixed> $frame Кадр.
     *
     * @return void
     */
    private function writeFrame(SseEmitter $sseEmitter, array $frame): void
    {
        $sseEmitter->writeEvent('sync', $frame);
        $this->lastWriteUnix = $this->sseClock->now()->toUnix();
    }

    /**
     * Комментарий раз в HEARTBEAT_SECONDS без MySQL.
     *
     * @param SseEmitter $sseEmitter Поток.
     *
     * @return void
     */
    private function writeHeartbeatIfDue(SseEmitter $sseEmitter): void
    {
        if ($this->sseClock->now()->toUnix() - $this->lastWriteUnix < self::HEARTBEAT_SECONDS) {
            return;
        }

        $sseEmitter->writeComment('ping');
        $this->lastWriteUnix = $this->sseClock->now()->toUnix();
    }

    /**
     * chatId сообщений без повторов.
     *
     * @param array<int, MessageRecord> $messageRecords Строки.
     *
     * @return array<int, int> Id чатов.
     */
    private function chatIdsOf(array $messageRecords): array
    {
        $chatIds = [];
        foreach ($messageRecords as $messageRecord) {
            $chatIds[$messageRecord->getChatId()] = $messageRecord->getChatId();
        }

        return array_values($chatIds);
    }

    /**
     * messages как объект JSON, не [].
     *
     * @param array<int, MessageRecord> $messageRecords Строки.
     *
     * @return stdClass|array<int, array<int, array<string, mixed>>> Карта.
     */
    private function messagesMap(array $messageRecords): stdClass|array
    {
        if ($messageRecords === []) {
            return new stdClass();
        }

        $namesByUserId = $this->namesByUserId($messageRecords);
        $messagesByChatId = [];
        foreach ($messageRecords as $messageRecord) {
            $chatId = $messageRecord->getChatId();
            $messagesByChatId[$chatId][] = $this->viewAssembler->assembleMessage(
                $messageRecord,
                $namesByUserId[$messageRecord->getUserId()] ?? '',
            );
        }

        return $messagesByChatId;
    }

    /**
     * Имена авторов пачкой.
     *
     * @param array<int, MessageRecord> $messageRecords Строки.
     *
     * @return array<int, string> user id → name.
     */
    private function namesByUserId(array $messageRecords): array
    {
        $userIds = [];
        foreach ($messageRecords as $messageRecord) {
            $userIds[$messageRecord->getUserId()] = $messageRecord->getUserId();
        }

        $namesByUserId = [];
        foreach ($this->userAccounts->getByIds(array_values($userIds)) as $userRecord) {
            $namesByUserId[$userRecord->getId()] = $userRecord->getName();
        }

        return $namesByUserId;
    }
}
