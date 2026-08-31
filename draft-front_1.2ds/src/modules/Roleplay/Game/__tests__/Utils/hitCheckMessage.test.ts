import { describe, expect, it } from 'vitest';
import { formatHitCheckMessage } from '@/modules/Roleplay/Game/Utils/hitCheckMessage';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const dagger: Rule = {
  id: null,
  code: 'dagger',
  type: 'item',
  name: 'Кинжал',
  description: '',
  spaceId: 1,
  keywordIds: [],
  mechanicId: null,
  createdAt: '2026-01-01T00:00:00Z',
};

const shield: Rule = { ...dagger, id: null, code: 'classic-shield', name: 'Классический щит' };

describe('formatHitCheckMessage', () => {
  it('уклон со ссылками', () => {
    expect(
      formatHitCheckMessage({
        attackerKey: 'character:3',
        attackerName: 'Гаррик из Тени',
        defenderKey: 'character:1',
        defenderName: 'Старый Бородач',
        weaponRuleCode: 'dagger',
        weaponName: 'Кинжал',
        reaction: 'dodge',
        rules: [dagger],
      }),
    ).toBe(
      '[[character:3,Гаррик из Тени]] совершает проверку на попадание оружием [[rule:dagger]] против [[character:1,Старый Бородач]]. Тот пытается уклониться!',
    );
  });

  it('блок со ссылкой на щит', () => {
    expect(
      formatHitCheckMessage({
        attackerKey: 'npc:2',
        attackerName: 'Ворон',
        defenderKey: 'character:1',
        defenderName: 'Бородач',
        weaponRuleCode: 'dagger',
        weaponName: 'Кинжал',
        reaction: 'block',
        blockItemRuleCode: 'classic-shield',
        rules: [dagger, shield],
      }),
    ).toContain('пытается блокировать, используя [[rule:classic-shield]]');
  });
});
