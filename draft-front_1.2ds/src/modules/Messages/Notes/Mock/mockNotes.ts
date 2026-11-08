import { getCurrentUserId } from '@/modules/Core/Auth/Mock/mockAuth';
import { users as realUsers } from '@/modules/Core/User/Mock/mockUsers';
import type { NewNote } from '@/modules/Messages/Notes/Dto/NewNote';
import type { Note } from '@/modules/Messages/Notes/Dto/Note';
import type { NoteFilter } from '@/modules/Messages/Notes/Dto/NoteFilter';
import type { NoteHostFilter } from '@/modules/Messages/Notes/Dto/NoteHostFilter';
import type { NoteHostGroup } from '@/modules/Messages/Notes/Dto/NoteHostGroup';
import type { NotePage } from '@/modules/Messages/Notes/Dto/NotePage';
import type { NotePatch } from '@/modules/Messages/Notes/Dto/NotePatch';
import { noteTextService } from '@/modules/Messages/Notes/Service/Instance/noteTextService';

type StoredNote = Omit<Note, 'authorName' | 'authorLogin' | 'canEdit' | 'canDelete'>;

const now = (): number => Math.floor(Date.now() / 1000);

const notes: StoredNote[] = [
  {
    id: 1,
    hostType: 'character',
    hostId: 1,
    authorId: 1,
    title: 'Что спросить у Гаррика',
    bodyHtml: '<p>Спросить про <strong>северный тракт</strong> и старую башню.</p>',
    bodyText: 'Спросить про северный тракт и старую башню.',
    pinned: true,
    sortOrder: 0,
    active: true,
    createdAt: now(),
    updatedAt: now(),
  },
  {
    id: 2,
    hostType: 'character',
    hostId: 1,
    authorId: 1,
    title: 'Долги',
    bodyHtml: '<p>Гаррик должен <em>две серебряные монеты</em>.</p>',
    bodyText: 'Гаррик должен две серебряные монеты.',
    pinned: false,
    sortOrder: 1,
    active: true,
    createdAt: now(),
    updatedAt: now(),
  },
  {
    id: 3,
    hostType: 'game',
    hostId: 1,
    authorId: 1,
    title: 'План на сессию',
    bodyHtml: '<p>Проверить руины на севере.</p>',
    bodyText: 'Проверить руины на севере.',
    pinned: false,
    sortOrder: 0,
    active: true,
    createdAt: now(),
    updatedAt: now(),
  },
  {
    id: 4,
    hostType: 'game',
    hostId: 2,
    authorId: 1,
    title: 'Старый план',
    bodyHtml: '<p>Отменённый маршрут на юг.</p>',
    bodyText: 'Отменённый маршрут на юг.',
    pinned: false,
    sortOrder: 0,
    active: false,
    createdAt: now() - 86400,
    updatedAt: now() - 86400,
  },
];

let nextNoteId = 5;

function actor(): { id: number; bypass: boolean } {
  const id = getCurrentUserId();
  const user = realUsers.find((item) => item.id === id);

  return { id, bypass: user?.bypass === true };
}

function authorName(authorId: number): string {
  const user = realUsers.find((item) => item.id === authorId);
  if (!user) return '';
  const name = [user.name, user.surname].filter(Boolean).join(' ');

  return name || user.login;
}

function authorLogin(authorId: number): string {
  return realUsers.find((item) => item.id === authorId)?.login ?? '';
}

function view(note: StoredNote): Note {
  const current = actor();

  return {
    ...note,
    authorName: authorName(note.authorId),
    authorLogin: authorLogin(note.authorId),
    canEdit: note.authorId === current.id && note.active,
    canDelete: note.authorId === current.id,
  };
}

function visible(note: StoredNote): boolean {
  const current = actor();

  return note.authorId === current.id || current.bypass;
}

function matchesQuery(note: StoredNote, query: string | undefined): boolean {
  if (!query) return true;
  const normalized = query.toLocaleLowerCase();

  return note.title.toLocaleLowerCase().includes(normalized) || note.bodyText.toLocaleLowerCase().includes(normalized);
}

function matchesState(note: StoredNote, state: NoteFilter['state']): boolean {
  if (state === 'archived') return !note.active;
  if (state === 'all') return true;

  return note.active;
}

function visibleNotes(
  filter: Pick<NoteFilter, 'q' | 'hostType' | 'hostId' | 'authorId' | 'pinned' | 'state'>,
): StoredNote[] {
  const current = actor();
  const authorId = current.bypass ? filter.authorId : current.id;

  return notes.filter(
    (note) =>
      visible(note) &&
      (authorId === undefined || note.authorId === authorId) &&
      (filter.hostType === undefined || note.hostType === filter.hostType) &&
      (filter.hostId === undefined || note.hostId === filter.hostId) &&
      (filter.pinned === undefined || note.pinned === filter.pinned) &&
      matchesState(note, filter.state) &&
      matchesQuery(note, filter.q),
  );
}

function byOrder(hostMode: boolean): (left: StoredNote, right: StoredNote) => number {
  if (hostMode) {
    return (left, right) =>
      Number(right.pinned) - Number(left.pinned) || left.sortOrder - right.sortOrder || left.id - right.id;
  }

  return (left, right) =>
    Number(right.pinned) - Number(left.pinned) || right.updatedAt - left.updatedAt || right.id - left.id;
}

function ownNote(noteId: number): StoredNote {
  const current = actor();
  const note = notes.find((item) => item.id === noteId);
  if (!note || (note.authorId !== current.id && !current.bypass)) throw new Error('NOTE_NOT_FOUND');
  if (note.authorId !== current.id && current.bypass) throw new Error('AUTH_DENIED');

  return note;
}

export async function mockFindPage(filter: NoteFilter): Promise<NotePage> {
  await Promise.resolve();
  const matched = visibleNotes(filter).sort(byOrder(filter.hostId !== undefined));
  const limit = filter.limit ?? 50;
  const offset = filter.offset ?? 0;

  return {
    items: matched.slice(offset, offset + limit).map(view),
    total: matched.length,
  };
}

export async function mockFindHosts(filter: NoteHostFilter): Promise<NoteHostGroup[]> {
  await Promise.resolve();
  const groups = new Map<string, NoteHostGroup>();
  for (const note of visibleNotes(filter)) {
    const key = `${note.hostType}:${note.hostId}`;
    const group = groups.get(key);
    if (group) {
      group.count += 1;
      group.lastUpdatedAt = Math.max(group.lastUpdatedAt, note.updatedAt);
    } else {
      groups.set(key, {
        hostType: note.hostType,
        hostId: note.hostId,
        count: 1,
        lastUpdatedAt: note.updatedAt,
      });
    }
  }

  return [...groups.values()]
    .sort((left, right) => right.lastUpdatedAt - left.lastUpdatedAt || right.hostId - left.hostId)
    .slice(0, filter.limit ?? 200);
}

export async function mockGet(id: number): Promise<Note> {
  await Promise.resolve();
  const current = actor();
  const note = notes.find((item) => item.id === id);
  if (!note || (note.authorId !== current.id && !current.bypass)) throw new Error('NOTE_NOT_FOUND');

  return view(note);
}

export async function mockCreate(data: NewNote): Promise<Note> {
  await Promise.resolve();
  const current = actor();
  const sortOrder =
    Math.max(
      -1,
      ...notes
        .filter(
          (note) =>
            note.authorId === current.id &&
            note.hostType === data.hostType &&
            note.hostId === data.hostId &&
            note.active,
        )
        .map((note) => note.sortOrder),
    ) + 1;
  const stamp = now();
  const note: StoredNote = {
    id: nextNoteId++,
    hostType: data.hostType,
    hostId: data.hostId,
    authorId: current.id,
    title: data.title.trim(),
    bodyHtml: data.bodyHtml ?? '',
    bodyText: noteTextService.toText(data.bodyHtml ?? ''),
    pinned: false,
    sortOrder,
    active: true,
    createdAt: stamp,
    updatedAt: stamp,
  };
  notes.push(note);

  return view(note);
}

export async function mockUpdate(id: number, patch: NotePatch): Promise<Note> {
  await Promise.resolve();
  const note = ownNote(id);
  if (!note.active) throw new Error('NOTE_INVALID');
  if (patch.title !== undefined) note.title = patch.title.trim();
  if (patch.bodyHtml !== undefined) {
    note.bodyHtml = patch.bodyHtml;
    note.bodyText = noteTextService.toText(patch.bodyHtml);
  }
  if (patch.pinned !== undefined) note.pinned = patch.pinned;
  note.updatedAt = now();

  return view(note);
}

export async function mockDelete(id: number): Promise<void> {
  await Promise.resolve();
  const note = ownNote(id);
  const index = notes.findIndex((item) => item.id === note.id);
  if (index >= 0) notes.splice(index, 1);
}

export async function mockReorder(hostType: string, hostId: number, noteIds: number[]): Promise<void> {
  await Promise.resolve();
  const current = actor();
  const activeIds = notes
    .filter(
      (note) => note.authorId === current.id && note.hostType === hostType && note.hostId === hostId && note.active,
    )
    .map((note) => note.id);
  const requested = [...noteIds].sort((left, right) => left - right);
  const expected = [...activeIds].sort((left, right) => left - right);
  if (requested.length !== expected.length || requested.some((id, index) => id !== expected[index])) {
    throw new Error('NOTE_INVALID');
  }
  noteIds.forEach((noteId, sortOrder) => {
    const note = notes.find((item) => item.id === noteId);
    if (note) {
      note.sortOrder = sortOrder;
      note.updatedAt = now();
    }
  });
}

export async function mockPin(id: number, pinned: boolean): Promise<Note> {
  await Promise.resolve();
  const note = ownNote(id);
  if (!note.active) throw new Error('NOTE_INVALID');
  note.pinned = pinned;
  note.updatedAt = now();

  return view(note);
}
