import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { INoteApi } from '@/modules/Messages/Notes/Interface/INoteApi';
import type { NewNote } from '@/modules/Messages/Notes/Dto/NewNote';
import type { Note } from '@/modules/Messages/Notes/Dto/Note';
import type { NoteFilter } from '@/modules/Messages/Notes/Dto/NoteFilter';
import type { NoteHostFilter } from '@/modules/Messages/Notes/Dto/NoteHostFilter';
import type { NoteHostGroup } from '@/modules/Messages/Notes/Dto/NoteHostGroup';
import type { NotePage } from '@/modules/Messages/Notes/Dto/NotePage';
import type { NotePatch } from '@/modules/Messages/Notes/Dto/NotePatch';

/**
 * HTTP-транспорт заметок.
 */
export class NoteApi implements INoteApi {
  constructor(private readonly engine: Engine) {}

  async findPage(filter: NoteFilter, signal?: AbortSignal): Promise<NotePage> {
    const res = await this.engine.runAction<NotePage>('note.findPage', filter, signal);
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Не удалось загрузить заметки');

    return res.data;
  }

  async findHosts(filter: NoteHostFilter, signal?: AbortSignal): Promise<NoteHostGroup[]> {
    const res = await this.engine.runAction<{ items: NoteHostGroup[] }>('note.findHosts', filter, signal);
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Не удалось загрузить сущности');

    return res.data.items;
  }

  async get(id: number, signal?: AbortSignal): Promise<Note> {
    const res = await this.engine.runAction<Note>('note.get', { noteId: id }, signal);
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Заметка не найдена');

    return res.data;
  }

  async create(data: NewNote, signal?: AbortSignal): Promise<Note> {
    const res = await this.engine.runAction<Note>('note.create', data, signal);
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Не удалось создать заметку');

    return res.data;
  }

  async update(id: number, patch: NotePatch, signal?: AbortSignal): Promise<Note> {
    const res = await this.engine.runAction<Note>('note.update', { noteId: id, ...patch }, signal);
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Не удалось сохранить заметку');

    return res.data;
  }

  async delete(id: number, signal?: AbortSignal): Promise<void> {
    const res = await this.engine.runAction('note.delete', { noteId: id }, signal);
    if (!res.success) throw new Error(res.error?.message ?? 'Не удалось удалить заметку');
  }

  async reorder(hostType: string, hostId: number, noteIds: number[], signal?: AbortSignal): Promise<void> {
    const res = await this.engine.runAction('note.reorder', { hostType, hostId, noteIds }, signal);
    if (!res.success) throw new Error(res.error?.message ?? 'Не удалось сохранить порядок');
  }

  async pin(id: number, pinned: boolean, signal?: AbortSignal): Promise<Note> {
    const res = await this.engine.runAction<Note>('note.pin', { noteId: id, pinned }, signal);
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Не удалось закрепить заметку');

    return res.data;
  }
}
