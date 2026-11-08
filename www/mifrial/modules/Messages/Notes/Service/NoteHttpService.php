<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Service;

use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Messages\Notes\Dto\Action\CreateNoteInput;
use Mifrial\Messages\Notes\Dto\Action\DeleteNoteInput;
use Mifrial\Messages\Notes\Dto\Action\FindNoteHostsInput;
use Mifrial\Messages\Notes\Dto\Action\FindNotePageInput;
use Mifrial\Messages\Notes\Dto\Action\GetNoteInput;
use Mifrial\Messages\Notes\Dto\Action\PinNoteInput;
use Mifrial\Messages\Notes\Dto\Action\ReorderNotesInput;
use Mifrial\Messages\Notes\Dto\Action\UpdateNoteInput;
use Mifrial\Messages\Notes\Dto\NewNote;
use Mifrial\Messages\Notes\Dto\NoteFilter;
use Mifrial\Messages\Notes\Dto\NoteHostFilter;
use Mifrial\Messages\Notes\Dto\NoteHostGroup;
use Mifrial\Messages\Notes\Dto\NotePatch;
use Mifrial\Messages\Notes\Interface\Service\INotes;

/**
 * HTTP-сценарии заметок: актор, фасад, JSON.
 */
final class NoteHttpService
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Guard.
     * @param INotes $notes Фасад.
     * @param NoteViewAssembler $viewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly INotes $notes,
        private readonly NoteViewAssembler $viewAssembler,
    ) {
    }

    /**
     * Страница заметок.
     *
     * @param FindNotePageInput $input Фильтр.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int} Страница.
     */
    public function findPage(FindNotePageInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $page = $this->notes->findPage($actor, NoteFilter::fromNormalized([
            'q' => $input->q,
            'hostType' => $input->hostType,
            'hostId' => $input->hostId,
            'authorId' => $input->authorId,
            'pinned' => $input->pinned,
            'state' => $input->state,
            'limit' => $input->limit,
            'offset' => $input->offset,
        ]));

        return [
            'items' => $this->viewAssembler->assembleMany($page->getItems(), $actor->getUserId()),
            'total' => $page->getTotal(),
        ];
    }

    /**
     * Сущности с заметками.
     *
     * @param FindNoteHostsInput $input Фильтр.
     *
     * @return array{items: array<int, array<string, mixed>>} Группы.
     */
    public function findHosts(FindNoteHostsInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $groups = $this->notes->findHosts($actor, NoteHostFilter::fromNormalized([
            'q' => $input->q,
            'hostType' => $input->hostType,
            'authorId' => $input->authorId,
            'state' => $input->state,
            'limit' => $input->limit,
        ]));

        return ['items' => array_map([$this, 'hostView'], $groups)];
    }

    /**
     * Одна заметка.
     *
     * @param GetNoteInput $input Заметка.
     *
     * @return array<string, mixed> JSON.
     */
    public function get(GetNoteInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->viewAssembler->assemble($this->notes->get($actor, $input->noteId), $actor->getUserId());
    }

    /**
     * Создаёт заметку.
     *
     * @param CreateNoteInput $input Поля.
     *
     * @return array<string, mixed> JSON.
     */
    public function create(CreateNoteInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $record = $this->notes->add($actor, NewNote::fromNormalized([
            'hostType' => $input->hostType,
            'hostId' => $input->hostId,
            'title' => $input->title,
            'bodyHtml' => $input->bodyHtml,
        ]));

        return $this->viewAssembler->assemble($record, $actor->getUserId());
    }

    /**
     * Обновляет заметку.
     *
     * @param UpdateNoteInput $input Поля.
     *
     * @return array<string, mixed> JSON.
     */
    public function update(UpdateNoteInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $record = $this->notes->update($actor, $input->noteId, NotePatch::fromNormalized([
            'title' => $input->title,
            'bodyHtml' => $input->bodyHtml,
            'pinned' => $input->pinned,
        ]));

        return $this->viewAssembler->assemble($record, $actor->getUserId());
    }

    /**
     * Удаляет заметку.
     *
     * @param DeleteNoteInput $input Заметка.
     *
     * @return array{deleted: true} Подтверждение.
     */
    public function delete(DeleteNoteInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $this->notes->delete($actor, $input->noteId);

        return ['deleted' => true];
    }

    /**
     * Пишет ручной порядок.
     *
     * @param ReorderNotesInput $input Порядок.
     *
     * @return array{reordered: int} Число заметок.
     */
    public function reorder(ReorderNotesInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $this->notes->reorder($actor, $input->hostType, $input->hostId, $input->noteIds);

        return ['reordered' => count($input->noteIds)];
    }

    /**
     * Меняет закрепление.
     *
     * @param PinNoteInput $input Заметка и флаг.
     *
     * @return array<string, mixed> JSON.
     */
    public function pin(PinNoteInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->viewAssembler->assemble(
            $this->notes->pin($actor, $input->noteId, $input->pinned),
            $actor->getUserId(),
        );
    }

    /**
     * JSON группы сущностей.
     *
     * @param NoteHostGroup $group Группа.
     *
     * @return array<string, mixed> JSON.
     */
    private function hostView(NoteHostGroup $group): array
    {
        return [
            'hostType' => $group->getHostType(),
            'hostId' => $group->getHostId(),
            'count' => $group->getCount(),
            'lastUpdatedAt' => $group->getLastUpdatedAt()->toUnix(),
        ];
    }
}
