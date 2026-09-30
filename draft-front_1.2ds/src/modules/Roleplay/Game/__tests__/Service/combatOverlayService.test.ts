import { describe, expect, it } from 'vitest';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';

function makeOverlay(): GameCombatOverlay {
  return { gameId: 2, entityKey: 'character:1', kind: 'character', updatedAt: '2026-08-19T12:00:00' };
}

describe('preferNewerCombatOverlays / replaceCombatOverlay', () => {
  it('не откатывает локальный оверлей пустым ответом сервера', () => {
    const local = makeOverlay();
    local.updatedAt = '2026-08-23T12:00:01';
    const stale = makeOverlay();
    stale.updatedAt = '';
    expect(combatOverlayService.preferNewerCombatOverlays([local], [stale])[0]).toEqual(local);
  });

  it('replaceCombatOverlay отдаёт новый массив с подменённым снимком', () => {
    const previous = makeOverlay();
    const next = makeOverlay();
    const list = combatOverlayService.replaceCombatOverlay([previous], next);
    expect(list).not.toBe([previous]);
    expect(list[0]).toEqual(next);
  });
});
