import { describe, it, expect } from 'vitest';
import { itemSpecService } from '@/modules/Roleplay/Rule/Service/Instance/itemSpecService';
import type { ItemSpecDraft } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpecDraft';

describe('ItemSpecService', () => {
  it('новый профиль оружия: урон и пробитие — actionCharacteristic (сила удара)', () => {
    const weapon = itemSpecService.ensureWeapon({
      category: 'equipment',
      cost_gm: 0,
      weight: null,
      special_rule_codes: [],
    });
    itemSpecService.addWeaponProfile(weapon, 'strength');
    const profile = weapon.weapon_profiles[0];
    expect(profile?.damage.formula).toMatchObject({
      type: 'actionCharacteristic',
      action: 'strike',
      characteristic: 'strength',
    });
    expect(profile?.penetration).toMatchObject({ type: 'actionCharacteristic', action: 'strike' });
  });

  it('innate: prune обнуляет стоимость и вес', () => {
    const draft: ItemSpecDraft = {
      category: 'equipment',
      cost_gm: 10,
      weight: { base: 3, size: 0 },
      special_rule_codes: [],
      innate: true,
    };

    expect(itemSpecService.prune(draft, [])).toMatchObject({ cost_gm: null, weight: null, innate: true });
  });
});
