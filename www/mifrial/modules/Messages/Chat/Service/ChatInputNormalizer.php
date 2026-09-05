<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Service;

use JsonException;
use Mifrial\Messages\Chat\Dto\MessageAudience;
use Mifrial\Messages\Chat\Dto\NewGroupChat;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;

/**
 * Разбор имени группы, текста send и JSON visibility HTTP.
 */
final class ChatInputNormalizer
{
    /**
     * Собирает NewGroupChat из сырого имени.
     *
     * @param string $name Имя; будет trim.
     * @param int $creatorId Создатель.
     * @param array<int, int> $memberIds Дополнительные члены.
     *
     * @return NewGroupChat Группа.
     *
     * @throws ChatInvalidException Если имя пусто или члены не int.
     */
    public function newGroupChat(string $name, int $creatorId, array $memberIds): NewGroupChat
    {
        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new ChatInvalidException('Group chat name is empty');
        }

        return NewGroupChat::fromNormalized([
            'name' => $trimmedName,
            'creatorId' => $creatorId,
            'memberIds' => $memberIds,
        ]);
    }

    /**
     * Нормализует текст и вложения send.
     *
     * @param string $content Текст.
     * @param array<int, mixed> $attachments Вложения.
     *
     * @return array{content: string, attachments: array<int, array{type: string, payload: mixed}>} Норма.
     *
     * @throws ChatInvalidException Если пусто или вложение недопустимо.
     */
    public function normalizeSend(string $content, array $attachments): array
    {
        $trimmedContent = trim($content);
        $normalizedAttachments = $this->normalizeAttachments($attachments);
        if ($trimmedContent === '' && $normalizedAttachments === []) {
            throw new ChatInvalidException('Message is empty');
        }

        return [
            'content' => $trimmedContent,
            'attachments' => $normalizedAttachments,
        ];
    }

    /**
     * JSON visibility → аудитория; члены чата уже известны.
     *
     * @param mixed $visibility JSON или null.
     * @param array<int, int> $memberIds Текущие члены.
     *
     * @return MessageAudience Аудитория.
     *
     * @throws ChatInvalidException Если объект недопустим или id не член.
     */
    public function normalizeAudience(mixed $visibility, array $memberIds): MessageAudience
    {
        // После json_decode assoc пустые {} и [] — один []; оба читаем как all.
        if ($visibility === null || $visibility === []) {
            return MessageAudience::all();
        }

        if (!is_array($visibility) || array_is_list($visibility)) {
            throw new ChatInvalidException('Message visibility is invalid');
        }

        $this->assertVisibilityObject($visibility);
        if (($visibility['all'] ?? false) === true) {
            return MessageAudience::all();
        }

        return MessageAudience::forUsers(
            $this->normalizeAudienceUserIds($visibility['forUsers'] ?? [], $memberIds),
        );
    }

    /**
     * Нормализует список вложений.
     *
     * @param array<int, mixed> $attachments Вход.
     *
     * @return array<int, array{type: string, payload: mixed}> Список.
     *
     * @throws ChatInvalidException Если элемент недопустим.
     */
    private function normalizeAttachments(array $attachments): array
    {
        $normalized = [];
        foreach ($attachments as $attachment) {
            $normalized[] = $this->normalizeAttachment($attachment);
        }

        return $normalized;
    }

    /**
     * Ключи объекта visibility.
     *
     * @param array<string, mixed> $visibility Объект.
     *
     * @return void
     *
     * @throws ChatInvalidException Если ключ недопустим.
     */
    private function assertVisibilityObject(array $visibility): void
    {
        $this->assertVisibilityKeys($visibility);
        $this->assertVisibilityAllSlot($visibility);
    }

    /**
     * forRole и неизвестные ключи.
     *
     * @param array<string, mixed> $visibility Объект.
     *
     * @return void
     *
     * @throws ChatInvalidException Если ключ недопустим.
     */
    private function assertVisibilityKeys(array $visibility): void
    {
        if (array_key_exists('forRole', $visibility)) {
            throw new ChatInvalidException('Message visibility is invalid');
        }

        foreach (array_keys($visibility) as $fieldName) {
            if ($fieldName !== 'all' && $fieldName !== 'forUsers') {
                throw new ChatInvalidException('Message visibility is invalid');
            }
        }
    }

    /**
     * all — bool; не вместе с forUsers.
     *
     * @param array<string, mixed> $visibility Объект.
     *
     * @return void
     *
     * @throws ChatInvalidException Если слот all недопустим.
     */
    private function assertVisibilityAllSlot(array $visibility): void
    {
        if (array_key_exists('all', $visibility) && !is_bool($visibility['all'])) {
            throw new ChatInvalidException('Message visibility is invalid');
        }

        if (($visibility['all'] ?? false) === true && array_key_exists('forUsers', $visibility)) {
            throw new ChatInvalidException('Message visibility is invalid');
        }
    }

    /**
     * List int ⊆ члены; дубли отбрасывает.
     *
     * @param mixed $forUsers JSON forUsers.
     * @param array<int, int> $memberIds Члены.
     *
     * @return array<int, int> Уникальные id.
     *
     * @throws ChatInvalidException Если не list int или чужой id.
     */
    private function normalizeAudienceUserIds(mixed $forUsers, array $memberIds): array
    {
        if (!is_array($forUsers) || !array_is_list($forUsers)) {
            throw new ChatInvalidException('Message visibility is invalid');
        }

        $memberSet = [];
        foreach ($memberIds as $memberId) {
            $memberSet[$memberId] = true;
        }

        $userIds = [];
        foreach ($forUsers as $userId) {
            if (!is_int($userId) || !isset($memberSet[$userId])) {
                throw new ChatInvalidException('Message visibility is invalid');
            }

            $userIds[$userId] = $userId;
        }

        return array_values($userIds);
    }

    /**
     * Одно вложение: type и payload, лишние ключи отбросить.
     *
     * @param mixed $attachment Элемент.
     *
     * @return array{type: string, payload: mixed} Вложение.
     *
     * @throws ChatInvalidException Если нет type или payload не JSON.
     */
    private function normalizeAttachment(mixed $attachment): array
    {
        if (!is_array($attachment)) {
            throw new ChatInvalidException('Attachment is invalid');
        }

        $attachmentType = trim(is_string($attachment['type'] ?? null) ? $attachment['type'] : '');
        if ($attachmentType === '') {
            throw new ChatInvalidException('Attachment type is empty');
        }

        $payload = $attachment['payload'] ?? null;
        $this->assertJsonCompatible($payload);

        return [
            'type' => $attachmentType,
            'payload' => $payload,
        ];
    }

    /**
     * Проверяет JSON-совместимость payload.
     *
     * @param mixed $payload Кандидат.
     *
     * @return void
     *
     * @throws ChatInvalidException Если json_encode падает.
     */
    private function assertJsonCompatible(mixed $payload): void
    {
        try {
            json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ChatInvalidException('Attachment payload is invalid', $exception);
        }
    }
}
