import { describe, expect, it, beforeEach } from 'vitest';
import { characterDraftPersistService } from '@/modules/Roleplay/Character/Service/Instance/characterDraftPersistService';
import { CHARACTER_DRAFT_STORAGE_KEY } from '@/modules/Roleplay/Character/Constant/characterDraftConfig';

describe('CharacterDraftPersistService', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('битый JSON сбрасывает ключ и помечает discarded', () => {
    localStorage.setItem(CHARACTER_DRAFT_STORAGE_KEY, '{not json');
    const result = characterDraftPersistService.read();
    expect(result.entries).toEqual([]);
    expect(result.discarded).toBe(true);
    expect(localStorage.getItem(CHARACTER_DRAFT_STORAGE_KEY)).toBeNull();
  });

  it('запись без build отбрасывается', () => {
    localStorage.setItem(CHARACTER_DRAFT_STORAGE_KEY, JSON.stringify([{ draftKey: 'character:1', config: {} }]));
    const result = characterDraftPersistService.read();
    expect(result.entries).toEqual([]);
    expect(result.discarded).toBe(true);
  });
});
