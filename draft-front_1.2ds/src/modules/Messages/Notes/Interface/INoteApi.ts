import type { NewNote } from '@/modules/Messages/Notes/Dto/NewNote';
import type { Note } from '@/modules/Messages/Notes/Dto/Note';
import type { NoteFilter } from '@/modules/Messages/Notes/Dto/NoteFilter';
import type { NoteHostFilter } from '@/modules/Messages/Notes/Dto/NoteHostFilter';
import type { NoteHostGroup } from '@/modules/Messages/Notes/Dto/NoteHostGroup';
import type { NotePage } from '@/modules/Messages/Notes/Dto/NotePage';
import type { NotePatch } from '@/modules/Messages/Notes/Dto/NotePatch';

export interface INoteApi {
  findPage(filter: NoteFilter, signal?: AbortSignal): Promise<NotePage>;
  findHosts(filter: NoteHostFilter, signal?: AbortSignal): Promise<NoteHostGroup[]>;
  get(id: number, signal?: AbortSignal): Promise<Note>;
  create(data: NewNote, signal?: AbortSignal): Promise<Note>;
  update(id: number, patch: NotePatch, signal?: AbortSignal): Promise<Note>;
  delete(id: number, signal?: AbortSignal): Promise<void>;
  reorder(hostType: string, hostId: number, noteIds: number[], signal?: AbortSignal): Promise<void>;
  pin(id: number, pinned: boolean, signal?: AbortSignal): Promise<Note>;
}
