import { describe, expect, it } from 'vitest';
import type { Note } from '@/modules/Messages/Notes/Dto/Note';
import { noteOrderService } from '@/modules/Messages/Notes/Service/Instance/noteOrderService';

function note(id: number, pinned = false): Note {
  return {
    id,
    hostType: 'character',
    hostId: 1,
    authorId: 1,
    authorName: 'Автор',
    authorLogin: 'author',
    title: `Заметка ${id}`,
    bodyHtml: '',
    bodyText: '',
    pinned,
    sortOrder: id,
    active: true,
    canEdit: true,
    canDelete: true,
    createdAt: 0,
    updatedAt: 0,
  };
}

function ids(notes: Note[]): number[] {
  return notes.map((item) => item.id);
}

describe('noteOrderService', () => {
  it('переносит заметку вверх с сохранением закреплённых сверху', () => {
    const notes = [note(1, true), note(2), note(3), note(4)];

    expect(noteOrderService.move(notes, 4, 2, 'before')).toEqual([1, 4, 2, 3]);
  });

  it('переносит заметку вниз', () => {
    const notes = [note(1, true), note(2), note(3), note(4)];

    expect(noteOrderService.move(notes, 2, 4, 'after')).toEqual([1, 3, 4, 2]);
  });

  it('drop на закреплённую ставит заметку в начало незакреплённых', () => {
    const notes = [note(1, true), note(2), note(3)];

    expect(noteOrderService.move(notes, 3, 1, 'after')).toEqual([1, 3, 2]);
  });

  it('не двигает заметку саму на себя и неизвестный id', () => {
    const notes = [note(1), note(2)];

    expect(noteOrderService.move(notes, 2, 2, 'before')).toBeNull();
    expect(noteOrderService.move(notes, 9, 1, 'before')).toBeNull();
    expect(noteOrderService.move(notes, 1, 9, 'before')).toBeNull();
  });

  it('не шлёт порядок, если он не изменился', () => {
    const notes = [note(1), note(2), note(3)];

    expect(noteOrderService.move(notes, 1, 2, 'before')).toBeNull();
  });

  it('не теряет заметки при переносе', () => {
    const notes = [note(1, true), note(2), note(3), note(4)];

    expect([...noteOrderService.move(notes, 3, 2, 'before')!].sort()).toEqual(ids(notes));
  });
});
