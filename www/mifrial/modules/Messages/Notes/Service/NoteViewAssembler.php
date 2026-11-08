<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Service;

use Mifrial\Core\User\Dto\UserRecord;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Messages\Notes\Dto\NoteRecord;

/**
 * JSON-вид заметки: права актора и имена авторов пачкой.
 */
final class NoteViewAssembler
{
    /**
     * Создаёт сборщик.
     *
     * @param IUserAccounts $userAccounts Учётки авторов.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccounts $userAccounts,
    ) {
    }

    /**
     * Одна заметка.
     *
     * @param NoteRecord $record Запись.
     * @param int $viewerId Читатель.
     *
     * @return array<string, mixed> JSON.
     */
    public function assemble(NoteRecord $record, int $viewerId): array
    {
        return $this->assembleMany([$record], $viewerId)[0];
    }

    /**
     * Пачка заметок с одним запросом авторов.
     *
     * @param array<int, NoteRecord> $records Записи.
     * @param int $viewerId Читатель.
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    public function assembleMany(array $records, int $viewerId): array
    {
        $authorIds = [];
        foreach ($records as $record) {
            $authorIds[$record->getAuthorId()] = true;
        }

        $users = $authorIds === [] ? [] : $this->userAccounts->getByIds(array_keys($authorIds));

        $items = [];
        foreach ($records as $record) {
            $author = $users[$record->getAuthorId()] ?? null;
            $isOwn = $record->getAuthorId() === $viewerId;
            $items[] = $this->view($record, $author, $isOwn);
        }

        return $items;
    }

    /**
     * Поля одной записи.
     *
     * @param NoteRecord $record Запись.
     * @param UserRecord|null $author Автор.
     * @param bool $isOwn Свой автор.
     *
     * @return array<string, mixed> JSON.
     */
    private function view(NoteRecord $record, ?UserRecord $author, bool $isOwn): array
    {
        return [
            'id' => $record->getId(),
            'hostType' => $record->getHostType(),
            'hostId' => $record->getHostId(),
            'authorId' => $record->getAuthorId(),
            'authorName' => $this->authorName($author),
            'authorLogin' => $author?->getLogin() ?? '',
            'title' => $record->getTitle(),
            'bodyHtml' => $record->getBodyHtml(),
            'bodyText' => $record->getBodyText(),
            'pinned' => $record->isPinned(),
            'sortOrder' => $record->getSortOrder(),
            'active' => $record->isActive(),
            'canEdit' => $isOwn && $record->isActive(),
            'canDelete' => $isOwn,
            'createdAt' => $record->getCreatedAt()->toUnix(),
            'updatedAt' => $record->getUpdatedAt()->toUnix(),
        ];
    }

    /**
     * Имя автора или логин.
     *
     * @param UserRecord|null $author Автор.
     *
     * @return string Подпись.
     */
    private function authorName(?UserRecord $author): string
    {
        if ($author === null) {
            return '';
        }

        $name = trim($author->getName() . ' ' . ($author->getSurname() ?? ''));

        return $name !== '' ? $name : $author->getLogin();
    }
}
