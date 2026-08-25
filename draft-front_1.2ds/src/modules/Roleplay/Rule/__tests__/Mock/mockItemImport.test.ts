import { describe, it, expect } from 'vitest';
import { mockItemImport } from '@/modules/Roleplay/Rule/Mock/mockItemImport';
import { keywords as mockKeywords } from '@/modules/Roleplay/Rule/Mock/mockKeywords';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';

const itemSpec = (rule: (typeof mockItemImport)[number]): ItemSpec => rule.spec as ItemSpec;

const kwId = (code: string): number => {
  const keyword = mockKeywords.find((k) => k.code === code);
  expect(keyword, `keyword ${code}`).toBeDefined();

  return keyword!.id;
};

describe('mockItemImport (S16, заход «Инвентарь»)', () => {
  it('уникальные id и code; все правила в ruleCatalog', () => {
    const ids = mockItemImport.map((rule) => rule.id);
    const codes = mockItemImport.map((rule) => rule.code);
    expect(new Set(ids).size).toBe(ids.length);
    expect(new Set(codes).size).toBe(codes.length);
    for (const rule of mockItemImport) {
      expect(ruleCatalog.some((entry) => entry.id === rule.id)).toBe(true);
    }
  });

  it('все предметы имеют category, keywordIds и признаки; у снаряжения цена — число', () => {
    const items = mockItemImport.filter((rule) => rule.type === 'item');
    for (const rule of items) {
      const spec = itemSpec(rule);
      expect(spec.category).toBeTruthy();
      expect(rule.keywordIds?.length).toBeGreaterThan(0);
      expect(rule.description.length).toBeGreaterThan(0);
    }
    // Кристаллы импортированы без цены (таблица прайса не разобрана); всё снаряжение/зелья — с ценой.
    const priced = items.filter((rule) => {
      const spec = itemSpec(rule);
      if (spec.innate) return false;

      return spec.weapon || spec.armor || spec.shield || spec.group_code;
    });
    for (const rule of priced) {
      expect(typeof itemSpec(rule).cost_gm).toBe('number');
    }
  });

  it('профили оружия: distance/range — Formula, damage — formula + тип, accuracy — размерное', () => {
    const weapons = mockItemImport.filter((rule) => itemSpec(rule).weapon);
    expect(weapons.length).toBeGreaterThanOrEqual(30);
    for (const rule of weapons) {
      const weapon = itemSpec(rule).weapon;
      expect(weapon).toBeTruthy();
      for (const profile of weapon!.weapon_profiles) {
        expect(profile.distance.type).toBeTruthy();
        expect(profile.damage.formula.type).toBeTruthy();
        expect(profile.accuracy).toHaveProperty('base');
      }
    }
  });

  it('доспехи имеют слоты защиты (в т.ч. поддоспешник), артефакты — group_code', () => {
    const armor = mockItemImport.filter((rule) => itemSpec(rule).armor);
    expect(armor.length).toBe(5);
    // Поддоспешник — слот того же доспеха (source armor) с высокой надёжностью 8 (труднее игнорировать).
    const underarmor = armor.some((rule) =>
      itemSpec(rule).armor!.defense_slots.some((slot) => slot.source_code === 'armor' && slot.durability === 8),
    );
    expect(underarmor).toBe(true);

    const artifacts = mockItemImport.filter((rule) => itemSpec(rule).group_code);
    expect(artifacts.length).toBeGreaterThanOrEqual(12);
  });

  it('оружие ссылается на семью владения (proficiency_family_code), семьи существуют', () => {
    const weapons = mockItemImport.filter((rule) => itemSpec(rule).weapon);
    const families = mockItemImport.filter((rule) => rule.type === 'weapon_family');
    expect(families.length).toBeGreaterThanOrEqual(20);
    for (const rule of weapons) {
      const code = itemSpec(rule).proficiency_family_code;
      expect(code).toBeTruthy();
      expect(families.some((family) => family.code === code)).toBe(true);
    }
    for (const family of families) {
      expect((family.spec as { costs?: number[] }).costs?.length).toBeGreaterThan(0);
    }
  });

  it('сила удара/выстрела в профилях выражается actionCharacteristic-формулами', () => {
    const ranged = mockItemImport
      .map((rule) => ({ rule, weapon: itemSpec(rule).weapon }))
      .filter(({ weapon }) => weapon?.weapon_profiles.some((p) => p.type === 'shoot'));
    expect(ranged.length).toBeGreaterThan(0);
    for (const { weapon } of ranged) {
      for (const profile of weapon!.weapon_profiles) {
        if (profile.type !== 'shoot') continue;
        if (profile.damage.formula.type === 'actionCharacteristic') {
          expect(profile.damage.formula.action).toBe('shoot');
        }
        if (profile.distance.type === 'actionCharacteristic') {
          expect(profile.distance.action).toBe('shoot');
        }
      }
    }
  });

  it('Щиты идут и с атакующим профилем, и с блоком щита', () => {
    const shields = mockItemImport.filter((rule) => itemSpec(rule).shield);
    expect(shields.length).toBe(3);
    for (const rule of shields) {
      const spec = itemSpec(rule);
      expect(spec.shield!.block).toHaveProperty('defense');
      expect(spec.shield!.block).toHaveProperty('efficiency');
      expect(spec.shield!.weapon_profiles?.length ?? 0).toBeGreaterThan(0);
    }
  });

  it('профили атак типизированы WeaponProfile', () => {
    const profile = (mockItemImport.find((r) => r.name === 'Кинжал')?.spec as ItemSpec)?.weapon?.weapon_profiles[0];
    const typed: WeaponProfile | undefined = profile;
    expect(typed?.type).toBe('strike');
  });

  it('признаки оружия из каталога: тип, свойства, подтип, собственное имя (Пата)', () => {
    const weapons = mockItemImport.filter((rule) => itemSpec(rule).weapon);
    for (const rule of weapons) {
      // Раздел + тип + свойства/подтип/имя — не меньше четырёх признаков.
      expect(rule.keywordIds!.length, rule.name).toBeGreaterThanOrEqual(4);
    }

    const pata = mockItemImport.find((rule) => rule.name === 'Пата');
    expect(pata).toBeDefined();
    const pataKw = pata!.keywordIds ?? [];
    expect(pataKw).toContain(kwId('pata'));
    expect(pataKw).toContain(kwId('weapon'));
    expect(pataKw).toContain(kwId('one-handed'));
    expect(pataKw).toContain(kwId('short'));
    expect(pataKw).toContain(kwId('blade'));
    expect(pataKw).toContain(kwId('metal'));
    // Признаки перенесены из description в keywords.
    expect(pata!.description).not.toContain('клинок, пата');
  });

  it('доспехи несут открытое лицо; латы — требует владения', () => {
    const leather = mockItemImport.find((rule) => rule.code === 'kozhanyy-dospekh');
    const plate = mockItemImport.find((rule) => rule.code === 'latnyy-dospekh');
    expect(leather?.keywordIds).toContain(kwId('open-face'));
    expect(plate?.keywordIds).toContain(kwId('open-face'));
    expect(plate?.keywordIds).toContain(kwId('requires-proficiency'));
  });

  it('естественное оружие: innate без цены, раздел «Естественное», семьи владения', () => {
    const ruka = mockItemImport.find((rule) => rule.code === 'ruka');
    const noga = mockItemImport.find((rule) => rule.code === 'noga');
    const famNoga = mockItemImport.find((rule) => rule.code === 'fam-noga');
    expect(itemSpec(ruka!).innate).toBe(true);
    expect(itemSpec(noga!).innate).toBe(true);
    expect(itemSpec(ruka!).cost_gm).toBeNull();
    expect(itemSpec(noga!).cost_gm).toBeNull();
    expect(ruka!.keywordIds).toContain(kwId('item-section-natural'));
    expect(noga!.keywordIds).toContain(kwId('item-section-natural'));
    expect(itemSpec(ruka!).proficiency_family_code).toBe('fam-kogti-ruki');
    expect(itemSpec(noga!).proficiency_family_code).toBe('fam-noga');
    expect(famNoga?.type).toBe('weapon_family');
  });

  it('все keywordIds предметов существуют в словаре признаков', () => {
    const ids = new Set(mockKeywords.map((k) => k.id));
    for (const rule of mockItemImport) {
      for (const keywordId of rule.keywordIds ?? []) {
        expect(ids.has(keywordId), `${rule.name}: #${keywordId}`).toBe(true);
      }
    }
  });
});
