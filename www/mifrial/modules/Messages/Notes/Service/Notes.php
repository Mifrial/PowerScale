<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Messages\Notes\Dto\NewNote;
use Mifrial\Messages\Notes\Dto\NoteFilter;
use Mifrial\Messages\Notes\Dto\NoteHostFilter;
use Mifrial\Messages\Notes\Dto\NoteHostGroup;
use Mifrial\Messages\Notes\Dto\NotePage;
use Mifrial\Messages\Notes\Dto\NotePatch;
use Mifrial\Messages\Notes\Dto\NoteRecord;
use Mifrial\Messages\Notes\Exception\NoteInvalidException;
use Mifrial\Messages\Notes\Exception\NoteNotFoundException;
use Mifrial\Messages\Notes\Interface\Service\INotes;
use Mifrial\Messages\Notes\Repository\NoteRepository;

/**
 * Сценарии личных заметок: авторство, bypass-чтение и ручной порядок.
 */
final class Notes implements INotes
{
    /**
     * Создаёт фасад.
     *
     * @param NoteRepository $repository Строки заметок.
     *
     * @return void
     */
    public function __construct(
        private readonly NoteRepository $repository,
    ) {
    }

    /**
     * Добавляет заметку от имени актора.
     *
     * @param RequestActor $actor Автор.
     * @param NewNote $note Поля.
     *
     * @return NoteRecord Запись.
     *
     * @throws NoteInvalidException Если поля недопустимы.
     */
    public function add(RequestActor $actor, NewNote $note): NoteRecord
    {
        return $this->repository->add($note, $actor->getUserId(), DateTime::now());
    }

    /**
     * Меняет заголовок, тело или закрепление своей заметки.
     *
     * @param RequestActor $actor Автор.
     * @param int $noteId Заметка.
     * @param NotePatch $patch Поля.
     *
     * @return NoteRecord После записи.
     *
     * @throws NoteInvalidException Если поля или состояние недопустимы.
     * @throws NoteNotFoundException Если заметки нет или она чужая.
     */
    public function update(RequestActor $actor, int $noteId, NotePatch $patch): NoteRecord
    {
        $record = $this->owned($actor, $noteId);
        if (!$record->isActive()) {
            throw new NoteInvalidException('Archived note is read-only');
        }

        if ($patch->isEmpty()) {
            throw new NoteInvalidException('Note patch is empty');
        }

        $values = [];
        if ($patch->getTitle() !== null) {
            $values['title'] = $patch->getTitle();
        }

        if ($patch->getBodyHtml() !== null) {
            $values['body_html'] = $patch->getBodyHtml();
            $values['body_text'] = NoteBodyText::derive($patch->getBodyHtml());
        }

        if ($patch->getPinned() !== null) {
            $values['pinned'] = $patch->getPinned();
        }

        return $this->repository->update($record, $values, DateTime::now());
    }

    /**
     * Удаляет свою заметку.
     *
     * @param RequestActor $actor Автор.
     * @param int $noteId Заметка.
     *
     * @return void
     *
     * @throws NoteNotFoundException Если заметки нет или она чужая.
     */
    public function delete(RequestActor $actor, int $noteId): void
    {
        $record = $this->owned($actor, $noteId);
        $this->repository->delete($record->getId());
    }

    /**
     * Читает свою или (bypass) чужую заметку.
     *
     * @param RequestActor $actor Читатель.
     * @param int $noteId Заметка.
     *
     * @return NoteRecord Запись.
     *
     * @throws NoteNotFoundException Если заметки нет или она чужая без bypass.
     */
    public function get(RequestActor $actor, int $noteId): NoteRecord
    {
        $record = $this->repository->getById($noteId);
        if ($record === null) {
            throw new NoteNotFoundException();
        }

        if ($record->getAuthorId() !== $actor->getUserId() && !$actor->hasBypass()) {
            throw new NoteNotFoundException();
        }

        return $record;
    }

    /**
     * Страница заметок с принудительным автором для обычного пользователя.
     *
     * @param RequestActor $actor Читатель.
     * @param NoteFilter $filter Фильтр.
     *
     * @return NotePage Страница.
     *
     * @throws NoteInvalidException Если фильтр недопустим.
     */
    public function findPage(RequestActor $actor, NoteFilter $filter): NotePage
    {
        return $this->repository->findPage($this->scopedFilter($actor, $filter));
    }

    /**
     * Сущности с заметками для левой колонки.
     *
     * @param RequestActor $actor Читатель.
     * @param NoteHostFilter $filter Фильтр.
     *
     * @return array<int, NoteHostGroup> Группы.
     *
     * @throws NoteInvalidException Если фильтр недопустим.
     */
    public function findHosts(RequestActor $actor, NoteHostFilter $filter): array
    {
        return $this->repository->findHosts(
            $actor->hasBypass() ? $filter : $filter->withAuthorId($actor->getUserId()),
        );
    }

    /**
     * Пишет ручной порядок активных заметок своей сущности.
     *
     * @param RequestActor $actor Автор.
     * @param string $hostType Тип сущности.
     * @param int $hostId Id сущности.
     * @param array<int, int> $noteIds Полный список id.
     *
     * @return void
     *
     * @throws NoteInvalidException Если список не совпадает с активными заметками.
     */
    public function reorder(RequestActor $actor, string $hostType, int $hostId, array $noteIds): void
    {
        $this->assertHost($hostType, $hostId);
        $orderedIds = $this->normalizeIds($noteIds);
        $currentIds = $this->repository->idsForHost($actor->getUserId(), $hostType, $hostId);
        sort($currentIds);
        $requestedIds = $orderedIds;
        sort($requestedIds);
        if ($currentIds !== $requestedIds) {
            throw new NoteInvalidException('Note order must list all active notes of the host');
        }

        $this->repository->reorder($orderedIds, DateTime::now());
    }

    /**
     * Меняет закрепление своей активной заметки.
     *
     * @param RequestActor $actor Автор.
     * @param int $noteId Заметка.
     * @param bool $pinned Новое значение.
     *
     * @return NoteRecord После записи.
     *
     * @throws NoteInvalidException Если заметка архивная.
     * @throws NoteNotFoundException Если заметки нет или она чужая.
     */
    public function pin(RequestActor $actor, int $noteId, bool $pinned): NoteRecord
    {
        $record = $this->owned($actor, $noteId);
        if (!$record->isActive()) {
            throw new NoteInvalidException('Archived note is read-only');
        }

        return $this->repository->pin($record, $pinned, DateTime::now());
    }

    /**
     * Гасит заметки удалённой или архивной сущности.
     *
     * @param string $hostType Тип сущности.
     * @param int $hostId Id сущности.
     *
     * @return int Число погашенных заметок.
     *
     * @throws NoteInvalidException Если значения недопустимы.
     */
    public function deactivateForHost(string $hostType, int $hostId): int
    {
        $this->assertHost($hostType, $hostId);

        return $this->repository->deactivateForHost($hostType, $hostId, DateTime::now());
    }

    /**
     * Заметка актора или отказ.
     *
     * @param RequestActor $actor Актор.
     * @param int $noteId Заметка.
     *
     * @return NoteRecord Своя запись.
     *
     * @throws NoteInvalidException Если запись чужая и у актора bypass.
     * @throws NoteNotFoundException Если записи нет или она чужая.
     */
    private function owned(RequestActor $actor, int $noteId): NoteRecord
    {
        $record = $this->repository->getById($noteId);
        if ($record === null) {
            throw new NoteNotFoundException();
        }

        if ($record->getAuthorId() === $actor->getUserId()) {
            return $record;
        }

        if ($actor->hasBypass()) {
            throw new ActionException('AUTH_DENIED', 'Bypass can only read foreign notes');
        }

        throw new NoteNotFoundException();
    }

    /**
     * Принудительный автор для обычного пользователя.
     *
     * @param RequestActor $actor Читатель.
     * @param NoteFilter $filter Фильтр.
     *
     * @return NoteFilter Фильтр.
     */
    private function scopedFilter(RequestActor $actor, NoteFilter $filter): NoteFilter
    {
        return $actor->hasBypass() ? $filter : $filter->withAuthorId($actor->getUserId());
    }

    /**
     * Проверяет хост.
     *
     * @param string $hostType Тип сущности.
     * @param int $hostId Id сущности.
     *
     * @return void
     *
     * @throws NoteInvalidException Если значения недопустимы.
     */
    private function assertHost(string $hostType, int $hostId): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $hostType) !== 1 || $hostId < 1) {
            throw new NoteInvalidException('Note host is invalid');
        }
    }

    /**
     * Проверяет список id порядка.
     *
     * @param array<int, int> $noteIds Список.
     *
     * @return array<int, int> Список.
     *
     * @throws NoteInvalidException Если список пуст, не list или с дублями.
     */
    private function normalizeIds(array $noteIds): array
    {
        if ($noteIds === [] || !array_is_list($noteIds)) {
            throw new NoteInvalidException('Note order is invalid');
        }

        $seen = [];
        foreach ($noteIds as $noteId) {
            if ($noteId < 1 || isset($seen[$noteId])) {
                throw new NoteInvalidException('Note order is invalid');
            }

            $seen[$noteId] = true;
        }

        return $noteIds;
    }
}
