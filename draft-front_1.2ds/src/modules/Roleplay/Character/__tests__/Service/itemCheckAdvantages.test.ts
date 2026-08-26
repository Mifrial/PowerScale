import { describe, it, expect } from 'vitest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import { itemCheckAdvantagesService } from '@/modules/Roleplay/Character/Service/Instance/itemCheckAdvantagesService';
import { mockItemImport } from '@/modules/Roleplay/Rule/Mock/mockItemImport';
import { mockModsImport } from '@/modules/Roleplay/Rule/Mock/mockModsImport';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';

const helm = mockModsImport.find((rule) => rule.code === 'closed-helm')!;
const plate = mockItemImport.find((rule) => rule.code === 'latnyy-dospekh')!;
const rules = [...ruleCatalog, ...mockModsImport];

function version(inventory: InventoryItem[]): CharacterVersion {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    raceRuleId: null,
    characteristics: [],
    resources: [],
    abilities: [],
    points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: 0 },
    money: 0,
    ageYears: null,
    inventory,
    states: [],
    senses: [],
  };
}

describe('checkAdvantageModifiersFromItems', () => {
  const helmed: InventoryItem = {
    id: 1,
    ruleId: plate.id,
    quantity: 1,
    equipped: true,
    modifierRuleIds: [helm.id],
  };

  it('экипированный шлем: помеха на внимательность от предмета, не на восприятие и не на попадание', () => {
    const sheet = version([helmed]);
    expect(
      itemCheckAdvantagesService.checkAdvantageModifiersFromItems(sheet, rules, {
        kind: 'characteristic',
        code: 'attention',
      }),
    ).toEqual([{ source_code: plate.id, source_label: 'Латный доспех', delta: -1 }]);
    expect(
      itemCheckAdvantagesService.checkAdvantageModifiersFromItems(sheet, rules, {
        kind: 'characteristic',
        code: 'perception',
      }),
    ).toEqual([]);
    expect(itemCheckAdvantagesService.checkAdvantageModifiersFromItems(sheet, rules, { kind: 'hit' })).toEqual([]);
  });

  it('не экипированный или без шлема — без помехи', () => {
    expect(
      itemCheckAdvantagesService.checkAdvantageModifiersFromItems(version([{ ...helmed, equipped: false }]), rules, {
        kind: 'characteristic',
        code: 'attention',
      }),
    ).toEqual([]);
    expect(
      itemCheckAdvantagesService.checkAdvantageModifiersFromItems(
        version([{ id: 1, ruleId: plate.id, quantity: 1, equipped: true }]),
        rules,
        {
          kind: 'characteristic',
          code: 'attention',
        },
      ),
    ).toEqual([]);
  });
});
