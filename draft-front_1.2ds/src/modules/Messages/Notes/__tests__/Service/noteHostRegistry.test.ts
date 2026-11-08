import { describe, it, expect, afterEach } from 'vitest';
import { getNoteHost, getNoteHosts, registerNoteHost } from '@/modules/Messages/Notes/init';
import { noteHostRegistry } from '@/modules/Messages/Notes/Service/Instance/noteHostRegistry';

afterEach(() => {
  noteHostRegistry.reset();
});

describe('note host registry', () => {
  it('registers a host and returns it by type', () => {
    registerNoteHost({ type: 'character', label: 'Персонаж', icon: 'mdi-account' });

    expect(getNoteHost('character')?.label).toBe('Персонаж');
    expect(getNoteHosts().map((host) => host.type)).toEqual(['character']);
  });

  it('returns undefined for an unknown type', () => {
    expect(getNoteHost('unknown')).toBeUndefined();
  });

  it('keeps a single entry per type on duplicate registration', () => {
    registerNoteHost({ type: 'character', label: 'Старый', icon: 'mdi-account' });
    registerNoteHost({ type: 'character', label: 'Новый', icon: 'mdi-account-outline' });

    expect(getNoteHosts()).toHaveLength(1);
    expect(getNoteHost('character')?.label).toBe('Новый');
  });

  it('keeps the editor context resolver', async () => {
    registerNoteHost({
      type: 'game',
      label: 'Игра',
      icon: 'mdi-dice-multiple',
      route: (id) => `/games/${id}`,
      resolveEditorContext: async (id) => ({
        inlineContext: { gameId: id },
        tokenSources: [],
      }),
    });

    const host = getNoteHost('game');
    expect(host?.route?.(7)).toBe('/games/7');
    await expect(host?.resolveEditorContext?.(7)).resolves.toEqual({
      inlineContext: { gameId: 7 },
      tokenSources: [],
    });
  });
});
