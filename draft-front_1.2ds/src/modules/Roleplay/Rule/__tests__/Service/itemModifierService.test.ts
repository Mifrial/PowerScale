import { describe, it, expect } from 'vitest';
import { reactive } from 'vue';
import { ItemModifierService } from '@/modules/Roleplay/Rule/Service/ItemModifierService';
import { mockModsImport } from '@/modules/Roleplay/Rule/Mock/mockModsImport';
import { mockItemImport } from '@/modules/Roleplay/Rule/Mock/mockItemImport';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemModifierApplies } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierSpec';
import type { ItemModifierPrice } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierSpec';

const service = new ItemModifierService();

function applies(partial: Partial<ItemModifierApplies> = {}): ItemModifierApplies {
  return {
    keyword_all: partial.keyword_all ?? [],
    keyword_any: partial.keyword_any ?? [],
    keyword_none: partial.keyword_none ?? [],
  };
}

function price(partial: Partial<ItemModifierPrice> = {}): ItemModifierPrice {
  return {
    factor: partial.factor ?? null,
    add_gm: partial.add_gm ?? null,
    add_gm_per_100g: partial.add_gm_per_100g ?? null,
    min_final_gm: partial.min_final_gm ?? null,
    by_keyword: partial.by_keyword ?? null,
  };
}

describe('ItemModifierService', () => {
  describe('isApplicable', () => {
    it('пустое all+any — применимо ко всем, none режет', () => {
      expect(service.isApplicable(applies(), ['weapon'])).toBe(true);
      expect(service.isApplicable(applies({ keyword_none: ['shield-item'] }), ['weapon', 'shield-item'])).toBe(false);
    });

    it('keyword_all требует все признаки; keyword_any — хотя бы один', () => {
      expect(service.isApplicable(applies({ keyword_all: ['weapon'] }), ['weapon'])).toBe(true);
      expect(service.isApplicable(applies({ keyword_all: ['weapon'] }), ['armor-item'])).toBe(false);
      expect(service.isApplicable(applies({ keyword_any: ['shield-item', 'armor-item'] }), ['armor-item'])).toBe(true);
      expect(service.isApplicable(applies({ keyword_any: ['shield-item', 'armor-item'] }), ['weapon'])).toBe(false);
    });

    it('щит с признаком weapon не проходит «только оружие» при none shield-item', () => {
      const weaponOnly = applies({ keyword_all: ['weapon'], keyword_none: ['shield-item'] });
      expect(service.isApplicable(weaponOnly, ['weapon'])).toBe(true);
      expect(service.isApplicable(weaponOnly, ['weapon', 'shield-item'])).toBe(false);
    });
  });

  describe('computePrice', () => {
    it('множители /2 /3 /1.2 и min_final', () => {
      expect(service.computePrice(100, price({ factor: 0.5 }), [], null)).toBe(50);
      expect(service.computePrice(100, price({ factor: 1 / 3 }), [], null)).toBe(33);
      expect(service.computePrice(120, price({ factor: 1 / 1.2 }), [], null)).toBe(100);
      expect(service.computePrice(100, price({ factor: 3, min_final_gm: 1000 }), [], null)).toBe(1000);
    });

    it('слагаемое и на 100 г веса; вес null → 0', () => {
      expect(service.computePrice(100, price({ add_gm: 250 }), [], null)).toBe(350);
      expect(service.computePrice(100, price({ add_gm_per_100g: 100 }), [], { base: 1, size: 0 })).toBe(1100);
      expect(service.computePrice(100, price({ add_gm_per_100g: 100 }), [], { base: 0.5, size: 0 })).toBe(600);
      expect(service.computePrice(100, price({ add_gm_per_100g: 100 }), [], null)).toBe(100);
    });

    it('by_keyword: приоритет armor-item, затем shield-item, затем weapon', () => {
      const poor = price({
        factor: 1 / 1.2,
        by_keyword: {
          'armor-item': { factor: 0.5 },
          'shield-item': { factor: 0.5 },
        },
      });
      expect(service.computePrice(120, poor, ['weapon'], null)).toBe(100);
      expect(service.computePrice(120, poor, ['weapon', 'shield-item'], null)).toBe(60);
      expect(service.computePrice(120, poor, ['armor-item', 'weapon'], null)).toBe(60);
    });

    it('стек двух модификаторов — слева направо', () => {
      const stacked = service.computeStack(100, [price({ factor: 2 }), price({ add_gm: 50 })], [], null);
      expect(stacked).toBe(250);
    });
  });

  describe('formatPriceLabel', () => {
    it('собирает множитель, слагаемые и порог', () => {
      expect(service.formatPriceLabel(price({ factor: 1.2, add_gm: 250 }))).toBe('×1.2 · +250 гм');
      expect(service.formatPriceLabel(price({ add_gm_per_100g: 100, min_final_gm: 1000 }))).toBe(
        '+100 гм / 100 г · минимум 1000 гм',
      );
      expect(service.formatPriceLabel(price())).toBeNull();
    });
  });

  describe('toggleSelection', () => {
    it('exclusive-тип заменяет модификатор того же типа', () => {
      const typeRule = {
        id: 't1',
        code: 'craft-quality',
        type: 'item_modifier_type' as const,
        name: 'Качество',
        description: '',
        spaceId: 1,
        keywordIds: [],
        mechanicId: null,
        createdAt: '2026-01-01T00:00:00Z',
        spec: { exclusive: true },
      };
      const poor = {
        ...typeRule,
        id: 'poor',
        code: 'mod-poor',
        type: 'item_modifier' as const,
        spec: {
          type_code: 'craft-quality',
          applies: applies(),
          price: price(),
          effects: [],
        },
      };
      const sturdy = { ...poor, id: 'sturdy', code: 'mod-sturdy' };
      const coating = {
        ...poor,
        id: 'coat',
        code: 'mod-coat',
        spec: { ...poor.spec, type_code: 'coating' },
      };

      expect(service.toggleSelection(['poor'], 'sturdy', [typeRule, poor, sturdy, coating])).toEqual(['sturdy']);
      expect(service.toggleSelection(['poor'], 'coat', [typeRule, poor, sturdy, coating])).toEqual(['poor', 'coat']);
      expect(service.toggleSelection(['poor'], 'poor', [typeRule, poor, sturdy])).toEqual([]);
    });
  });

  describe('applyStack', () => {
    const dagger = mockItemImport.find((rule) => rule.code === 'kinzhal')!;
    const plate = mockItemImport.find((rule) => rule.code === 'latnyy-dospekh')!;
    const weighted = mockModsImport.find((rule) => rule.code === 'weighted')!;
    const poorly = mockModsImport.find((rule) => rule.code === 'poorly-made')!;
    const silvered = mockModsImport.find((rule) => rule.code === 'silvered')!;
    const breastplate = mockModsImport.find((rule) => rule.code === 'breastplate-only')!;

    function specOf(rule: Rule): ItemSpec {
      return rule.spec as ItemSpec;
    }

    function damageDeltas(item: ItemSpec): { type: string; formulaType: string; deltas: number[] }[] {
      return (item.weapon?.weapon_profiles ?? []).map((profile) => ({
        type: profile.type,
        formulaType: profile.damage.formula.type,
        deltas:
          profile.damage.formula.type === 'actionCharacteristic'
            ? profile.damage.formula.modifier.map((m) => m.delta)
            : [],
      }));
    }

    it('утяжелённый кинжал: вес, блок, мин. сила, сила удара; фикс без изменений', () => {
      const { spec, cost } = service.applyStack(specOf(dagger), [weighted], ['weapon']);
      expect(spec.weight).toEqual({ base: 0.625, size: 0 });
      expect(spec.weapon?.block_profile?.defense).toEqual({ base: 3.75, size: 0 });
      expect(spec.weapon?.min_strength).toEqual({ base: 3, size: 1 });
      expect(cost).toBe(1200);
      const rows = damageDeltas(spec);
      const slash = rows.find((row) => row.formulaType === 'actionCharacteristic' && row.type === 'strike');
      const thrown = rows.find((row) => row.type === 'throw');
      const fixed = rows.filter((row) => row.formulaType === 'fixed');
      expect(slash?.deltas).toEqual([-3, 1]);
      expect(thrown?.deltas).toEqual([-6, 1]);
      expect(fixed.every((row) => row.deltas.length === 0)).toBe(true);
    });

    it('плохо сделан: прочность на размер вниз и помеха от инструмента', () => {
      const { spec } = service.applyStack(specOf(dagger), [poorly], ['weapon']);
      expect(spec.weapon?.durability).toEqual({ base: 5, size: 0 });
      expect(spec.advantages).toEqual([{ source_code: 'tool', source_label: 'Плохо сделан', delta: -1 }]);
    });

    it('посеребрение: сопротивление магии до 1', () => {
      const { spec } = service.applyStack(specOf(dagger), [silvered], ['weapon']);
      expect(spec.weapon?.block_profile?.resistances).toEqual([
        { damage_type_code: 'magic-damage', value: { base: 1, size: 0 }, durability: 1, source_code: null },
      ]);
    });

    it('нагрудник: вес ¼, надёжность 1, штраф снят', () => {
      const { spec } = service.applyStack(specOf(plate), [breastplate], ['armor-item']);
      expect(spec.weight).toEqual({ base: 6.25, size: 0 });
      expect(spec.armor?.defense_slots.every((slot) => slot.durability === 1)).toBe(true);
      expect(spec.armor?.strength_penalty).toBeNull();
      expect(spec.armor?.max_agility).toEqual({ base: 4, size: -1 });
    });

    it('два мода: цена посеребрения от уже увеличенного веса', () => {
      const { spec, cost } = service.applyStack(specOf(dagger), [weighted, silvered], ['weapon']);
      expect(spec.weight).toEqual({ base: 0.625, size: 0 });
      expect(cost).toBe(1800);
    });

    it('клонирует Vue-прокси спека без structuredClone-ошибки', () => {
      const proxied = reactive(specOf(dagger));
      expect(() => service.applyStack(proxied, [weighted], ['weapon'])).not.toThrow();
      expect(proxied.weight).toEqual(specOf(dagger).weight);
    });

    it('импровизированное одно — цена 0; с другими модами — /4 каталога', () => {
      const staff = mockItemImport.find((rule) => rule.code === 'boevoy-posokh')!;
      const improvised = mockModsImport.find((rule) => rule.code === 'improvised')!;
      const { cost: alone } = service.applyStack(specOf(staff), [improvised], ['weapon', 'improvable']);
      expect(alone).toBe(0);
      const { cost: mixed } = service.applyStack(specOf(staff), [improvised, weighted], ['weapon', 'improvable']);
      expect(mixed).toBe(75);
    });

    it('трудное / простое / очень трудное множит цену качества изделия', () => {
      const tekko = mockItemImport.find((rule) => rule.code === 'tekko-kagi')!;
      const whip = mockItemImport.find((rule) => rule.code === 'knut')!;
      const flamberge = mockItemImport.find((rule) => rule.code === 'flamberg')!;
      const excellent = mockModsImport.find((rule) => rule.code === 'excellently-made')!;
      const { cost: hard } = service.applyStack(specOf(tekko), [excellent], ['weapon', 'hard-to-craft']);
      expect(hard).toBe(10000);
      const { cost: easy } = service.applyStack(specOf(whip), [poorly], ['weapon', 'easy-to-craft']);
      expect(easy).toBe(75);
      const { cost: very } = service.applyStack(specOf(flamberge), [excellent], ['weapon', 'very-hard-to-craft']);
      expect(very).toBe(90000);
    });

    it('оковка: металл, минимум 2 ОД; шлем +1 надёжности и помеха на внимательность', () => {
      const staff = mockItemImport.find((rule) => rule.code === 'boevoy-posokh')!;
      const ferrule = mockModsImport.find((rule) => rule.code === 'steel-ferrule')!;
      const helm = mockModsImport.find((rule) => rule.code === 'closed-helm')!;
      const { spec, keywordCodes } = service.applyStack(specOf(staff), [ferrule], ['weapon', 'staff']);
      expect(spec.weapon?.min_action_cost).toBe(2);
      expect(keywordCodes).toEqual(expect.arrayContaining(['weapon', 'staff', 'metal']));
      const { spec: helmed, keywordCodes: afterHelm } = service.applyStack(specOf(plate), [helm], ['armor-item']);
      expect(afterHelm).not.toContain('limited-visibility');
      expect(helmed.armor?.defense_slots.map((slot) => slot.durability)).toEqual([4, 7]);
      expect(helmed.check_advantages).toEqual([{ delta: -1, characteristic_codes: ['attention'] }]);
    });

    it('золото — проводник магии 10; носорог удваивает удорожание качества', () => {
      const gold = mockModsImport.find((rule) => rule.code === 'gold')!;
      const rhino = mockModsImport.find((rule) => rule.code === 'stonehide-rhino-leather')!;
      const good = mockModsImport.find((rule) => rule.code === 'good-quality')!;
      const leather = mockItemImport.find((rule) => rule.code === 'kozhanyy-dospekh')!;
      const { spec } = service.applyStack(specOf(dagger), [gold], ['weapon', 'metal']);
      expect(spec.magic_conductor).toBe(10);
      const { cost } = service.applyStack(specOf(leather), [good, rhino], ['armor-item', 'leather']);
      expect(cost).toBe(34000);
    });
  });
});
