import { describe, it, expect, afterEach } from 'vitest';
import { mockLogin, mockLogout } from '@/modules/Core/Auth/Mock/mockAuth';
import {
  mockCreate,
  mockDelete,
  mockFindHosts,
  mockFindPage,
  mockGet,
  mockPin,
  mockReorder,
  mockUpdate,
} from '@/modules/Messages/Notes/Mock/mockNotes';
import type { NoteFilter } from '@/modules/Messages/Notes/Dto/NoteFilter';

const ownerLogin = 'ivan_p';

function filter(values: NoteFilter): NoteFilter {
  return { state: 'active', limit: 100, offset: 0, ...values };
}

afterEach(async () => {
  await mockLogout();
});

describe('mock notes ownership', () => {
  it('returns only own notes for a regular user', async () => {
    await mockLogin(ownerLogin, 'test');

    const page = await mockFindPage(filter({ hostType: 'character', hostId: 1 }));

    expect(page.total).toBeGreaterThan(0);
    expect(page.items.every((note) => note.authorId === 1)).toBe(true);
    expect(page.items.every((note) => note.canEdit || !note.active)).toBe(true);
  });

  it('lets bypass read foreign notes without edit rights', async () => {
    await mockLogin('admin', 'test');

    const page = await mockFindPage(filter({ hostType: 'character', hostId: 1, authorId: 1 }));
    const foreign = page.items.find((note) => note.authorId === 1);

    expect(foreign).toBeDefined();
    expect(foreign?.canEdit).toBe(false);
    expect(foreign?.canDelete).toBe(false);
  });

  it('hides foreign notes from a regular user by get', async () => {
    await mockLogin(ownerLogin, 'test');
    const page = await mockFindPage(filter({ hostType: 'character', hostId: 1 }));
    const foreignId = page.items[0]?.id;
    expect(foreignId).toBeDefined();

    await mockLogin('no_mail', 'test');

    await expect(mockGet(foreignId as number)).rejects.toThrow('NOTE_NOT_FOUND');
  });
});

describe('mock notes crud', () => {
  it('creates at the end of the host list and derives body text', async () => {
    await mockLogin(ownerLogin, 'test');
    const hostId = 9001;

    const first = await mockCreate({ hostType: 'game', hostId, title: '  Первая  ', bodyHtml: '<p>север</p>' });
    const second = await mockCreate({ hostType: 'game', hostId, title: 'Вторая', bodyHtml: '<p>юг</p>' });

    expect(first.title).toBe('Первая');
    expect(first.bodyText).toBe('север');
    expect(first.sortOrder).toBe(0);
    expect(second.sortOrder).toBe(1);
    expect(first.canEdit).toBe(true);

    const page = await mockFindPage(filter({ hostType: 'game', hostId }));
    expect(page.items.map((note) => note.id)).toEqual([first.id, second.id]);

    await mockDelete(first.id);
    await mockDelete(second.id);
  });

  it('updates, pins and reorders active notes', async () => {
    await mockLogin(ownerLogin, 'test');
    const hostId = 9002;
    const first = await mockCreate({ hostType: 'game', hostId, title: 'A' });
    const second = await mockCreate({ hostType: 'game', hostId, title: 'B' });

    const updated = await mockUpdate(first.id, { title: 'A2', bodyHtml: '<p>текст</p>' });
    expect(updated.title).toBe('A2');
    expect(updated.bodyText).toBe('текст');

    const pinned = await mockPin(second.id, true);
    expect(pinned.pinned).toBe(true);

    await mockReorder('game', hostId, [second.id, first.id]);
    const page = await mockFindPage(filter({ hostType: 'game', hostId }));
    expect(page.items.map((note) => note.id)).toEqual([second.id, first.id]);

    await mockDelete(first.id);
    await mockDelete(second.id);
  });

  it('rejects a reorder that does not list every active note', async () => {
    await mockLogin(ownerLogin, 'test');
    const hostId = 9003;
    const first = await mockCreate({ hostType: 'game', hostId, title: 'A' });
    const second = await mockCreate({ hostType: 'game', hostId, title: 'B' });

    await expect(mockReorder('game', hostId, [first.id])).rejects.toThrow('NOTE_INVALID');

    await mockDelete(first.id);
    await mockDelete(second.id);
  });
});

describe('mock notes search and groups', () => {
  it('searches by title and body text', async () => {
    await mockLogin(ownerLogin, 'test');
    const hostId = 9004;
    const alpha = await mockCreate({ hostType: 'map', hostId, title: 'Альфа', bodyHtml: '<p>северный тракт</p>' });
    const beta = await mockCreate({ hostType: 'map', hostId, title: 'Бета', bodyHtml: '<p>южный тракт</p>' });

    const byTitle = await mockFindPage(filter({ q: 'альфа' }));
    expect(byTitle.items.some((note) => note.id === alpha.id)).toBe(true);
    expect(byTitle.items.some((note) => note.id === beta.id)).toBe(false);

    const byBody = await mockFindPage(filter({ q: 'южный' }));
    expect(byBody.items.some((note) => note.id === beta.id)).toBe(true);
    expect(byBody.items.some((note) => note.id === alpha.id)).toBe(false);

    await mockDelete(alpha.id);
    await mockDelete(beta.id);
  });

  it('groups hosts by type and id with counts', async () => {
    await mockLogin(ownerLogin, 'test');
    const hostId = 9005;
    const first = await mockCreate({ hostType: 'game', hostId, title: 'A' });
    const second = await mockCreate({ hostType: 'game', hostId, title: 'B' });
    const third = await mockCreate({ hostType: 'map', hostId, title: 'C' });

    const groups = await mockFindHosts({ state: 'active', limit: 500 });
    const byKey = new Map(groups.map((group) => [`${group.hostType}:${group.hostId}`, group.count]));

    expect(byKey.get(`game:${hostId}`)).toBe(2);
    expect(byKey.get(`map:${hostId}`)).toBe(1);

    await mockDelete(first.id);
    await mockDelete(second.id);
    await mockDelete(third.id);
  });
});
