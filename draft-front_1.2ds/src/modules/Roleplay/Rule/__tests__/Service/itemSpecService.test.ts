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
    expect(profile?.dodge_benefit).toBeUndefined();
  });

  it('innate: applyInnateLimits обнуляет стоимость и вес', () => {
    const draft: ItemSpecDraft = {
      category: 'equipment',
      cost_gm: 10,
      weight: { base: 3, size: 0 },
      special_rule_codes: [],
      innate: true,
    };

    expect(itemSpecService.applyInnateLimits(draft)).toMatchObject({ cost_gm: null, weight: null, innate: true });
  });

  it('вес: килограммы в тики по 0.5 кг при size −5', () => {
    expect(itemSpecService.kilogramsToWeight(0.5)).toEqual({ base: 1, size: -5 });
    expect(itemSpecService.kilogramsToWeight(3.5)).toEqual({ base: 7, size: -5 });
    expect(itemSpecService.weightToKilograms({ base: 7, size: -5 })).toBe(3.5);
    expect(itemSpecService.formatWeightKilograms({ base: 7, size: -5 })).toBe('3.5 кг');
  });

  it('занятость рук: «1–2 руки»', () => {
    expect(itemSpecService.formatOccupyHands({ min: 1, max: 2 })).toBe('1–2 руки');
    expect(itemSpecService.formatOccupyHands({ min: 1, max: 1 })).toBe('1 руку');
    expect(itemSpecService.formatOccupyHands({ min: 2, max: 2 })).toBe('2 руки');
    expect(itemSpecService.formatOccupyHands({ min: 0, max: 0 })).toBeNull();
    expect(itemSpecService.formatOccupyHands(undefined)).toBeNull();
  });
});
