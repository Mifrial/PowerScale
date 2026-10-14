import { describe, expect, it } from 'vitest';
import { gamePhpCommandService } from '@/modules/Roleplay/Game/Service/Instance/gamePhpCommandService';

describe('GamePhpCommandService', () => {
  it('выбирает wide только при двух разных целях', () => {
    expect(gamePhpCommandService.isWideStrike('wide', ['character:1'])).toBe(false);
    expect(gamePhpCommandService.isWideStrike('wide', ['character:1', 'npc:2'])).toBe(true);
    expect(gamePhpCommandService.isWideStrike('single', ['character:1', 'npc:2'])).toBe(true);
    expect(gamePhpCommandService.isWideStrike(undefined, ['character:1', 'character:1'])).toBe(false);
    expect(gamePhpCommandService.uniqueTargetKeys(['npc:1', 'npc:2', 'npc:1'])).toEqual(['npc:1', 'npc:2']);
  });

  it('для раны шлёт kind, для удара type', () => {
    expect(gamePhpCommandService.woundPartyOf('character:4')).toEqual({ kind: 'character', id: 4 });
    expect(gamePhpCommandService.woundPartyFromHeldBy('npc:9')).toEqual({ kind: 'npc', id: 9 });
    expect(gamePhpCommandService.woundPartyFromHeldBy({ kind: 'character', id: 4 })).toEqual({
      kind: 'character',
      id: 4,
    });
    expect(gamePhpCommandService.participantOf('npc:9')).toEqual({ type: 'npc', id: 9 });
  });
});
