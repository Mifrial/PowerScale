import { describe, expect, it } from 'vitest';
import { fetchQuickRolls, addQuickRoll, removeQuickRoll } from '@/modules/Roleplay/Game/Mock/mockGameQuickRolls';
import { combatKey } from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';

const charKey = combatKey('character', 1);
const npcKey = combatKey('npc', 5);

describe('mockGameQuickRolls: быстрые броски (макросы)', () => {
  it('addQuickRoll добавляет характеристику; дубликаты игнорируются', async () => {
    const first = await addQuickRoll(2, charKey, 'rule-3');
    expect(first).toEqual(['rule-3']);
    const second = await addQuickRoll(2, charKey, 'rule-3');
    expect(second).toEqual(['rule-3']);
    const withAnother = await addQuickRoll(2, charKey, 'rule-7');
    expect(withAnother).toEqual(['rule-3', 'rule-7']);
  });

  it('быстрые броски изолированы по сущности и игре', async () => {
    await addQuickRoll(2, npcKey, 'rule-42');
    const all = await fetchQuickRolls(2);
    expect(all[charKey]).toEqual(['rule-3', 'rule-7']);
    expect(all[npcKey]).toEqual(['rule-42']);
    const other = await fetchQuickRolls(1);
    expect(other[charKey]).toBeUndefined();
  });

  it('removeQuickRoll убирает характеристику; повторное удаление безопасно', async () => {
    const after = await removeQuickRoll(2, charKey, 'rule-3');
    expect(after).toEqual(['rule-7']);
    const again = await removeQuickRoll(2, charKey, 'rule-3');
    expect(again).toEqual(['rule-7']);
  });
});
