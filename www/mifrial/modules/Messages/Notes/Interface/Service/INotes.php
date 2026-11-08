<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Interface\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Messages\Notes\Dto\NewNote;
use Mifrial\Messages\Notes\Dto\NoteFilter;
use Mifrial\Messages\Notes\Dto\NoteHostFilter;
use Mifrial\Messages\Notes\Dto\NoteHostGroup;
use Mifrial\Messages\Notes\Dto\NotePage;
use Mifrial\Messages\Notes\Dto\NotePatch;
use Mifrial\Messages\Notes\Dto\NoteRecord;
use Mifrial\Messages\Notes\Exception\NoteInvalidException;
use Mifrial\Messages\Notes\Exception\NoteNotFoundException;

/**
 * Фасад личных заметок: без схемы и без HTTP.
 */
interface INotes
{
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
    public function add(RequestActor $actor, NewNote $note): NoteRecord;

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
    public function update(RequestActor $actor, int $noteId, NotePatch $patch): NoteRecord;

    /**
     * Удаляет свою заметку.
     *
     * @param RequestActor $actor Автор.
     * @param int $noteId Заметка.
     *
     * @return void
     *
     * @throws NoteInvalidException Если состояние недопустимо.
     * @throws NoteNotFoundException Если заметки нет или она чужая.
     */
    public function delete(RequestActor $actor, int $noteId): void;

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
    public function get(RequestActor $actor, int $noteId): NoteRecord;

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
    public function findPage(RequestActor $actor, NoteFilter $filter): NotePage;

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
    public function findHosts(RequestActor $actor, NoteHostFilter $filter): array;

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
    public function reorder(RequestActor $actor, string $hostType, int $hostId, array $noteIds): void;

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
    public function pin(RequestActor $actor, int $noteId, bool $pinned): NoteRecord;

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
    public function deactivateForHost(string $hostType, int $hostId): int;
}
