import type { Note } from '@/modules/Messages/Notes/Dto/Note';
import type { NoteDropSide } from '@/modules/Messages/Notes/Enum/NoteDropSide';

/** Ручной порядок заметок: закреплённые всегда сверху, остальные — по месту drop. */
export class NoteOrderService {
  /**
   * Возвращает новый полный список id после переноса или null, если перенос невозможен.
   *
   * Закреплённые заметки остаются сверху; перетаскиваемая встаёт до/после цели.
   * Drop на закреплённую — вставка в начало блока незакреплённых.
   */
  move(notes: Note[], draggedId: number, targetId: number, side: NoteDropSide): number[] | null {
    if (draggedId === targetId) return null;
    const pinned = notes.filter((note) => note.pinned);
    const movable = notes.filter((note) => !note.pinned);
    const from = movable.findIndex((note) => note.id === draggedId);
    if (from < 0) return null;
    const target = notes.find((note) => note.id === targetId);
    if (!target) return null;

    const [moved] = movable.splice(from, 1);
    const targetIndex = target.pinned ? -1 : movable.findIndex((note) => note.id === targetId);
    const insertAt = targetIndex < 0 ? 0 : side === 'before' ? targetIndex : targetIndex + 1;
    movable.splice(insertAt, 0, moved);

    const ordered = [...pinned, ...movable];
    if (ordered.every((note, index) => note.id === notes[index]?.id)) return null;

    return ordered.map((note) => note.id);
  }
}
