import { describe, it, expect } from 'vitest';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { itemMasteryService } from '@/modules/Roleplay/Character/Service/Instance/itemMasteryService';
import { weaponProficiencyService } from '@/modules/Roleplay/Character/Service/Instance/weaponProficiencyService';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';

const knife = ruleCatalog.find((r) => r.name === 'Кинжал');
const knifeSpec = knife?.spec as ItemSpec;
const masteryRule = ruleCatalog.find((r) => r.code === 'vladenie-oruzhiem');
const family = ruleCatalog.find((r) => r.code === 'fam-kinzhal-nozh');

function build(abilities: CharacterBuild['abilities'] = []): CharacterBuild {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceId: 1,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    raceRuleCode: null,
    characteristicPurchases: [],
    abilities,
    resources: [],
    inventory: [],
    states: [],
    money: 0,
    ageYears: null,
    olTotal: 0,
  };
}

describe('itemMasteryView (блок владения в панели предмета)', () => {
  it('семья, лестница и правило владения из ревизии; уровень 0', () => {
    expect(knife).toBeDefined();
    expect(masteryRule).toBeDefined();
    expect(family).toBeDefined();

    const view = itemMasteryService.itemMasteryView(knifeSpec, build().abilities, ruleCatalog);

    expect(view).toEqual({
      masteryRuleCode: masteryRule!.code,
      familyName: family!.name,
      familyCode: 'fam-kinzhal-nozh',
      ladder: [1, 3, 5],
      level: 0,
      maxLevel: 3,
    });
  });

  it('уровень берётся из экземпляра владения семьи', () => {
    const abilities = [{ ruleCode: masteryRule!.code, level: 2, domain: family!.name, domainCode: family!.code }];

    const view = itemMasteryService.itemMasteryView(knifeSpec, build(abilities).abilities, ruleCatalog);

    expect(view?.level).toBe(2);
  });

  it('прокачка через сервис: взять 1 → прокачать до 3 → снять', () => {
    let current = build();
    const mastery = itemMasteryService.itemMasteryView(knifeSpec, current.abilities, ruleCatalog);
    expect(mastery).not.toBeNull();

    // взять уровень 1
    current = characterBuildService.setWeaponMastery(
      current,
      mastery!.masteryRuleCode,
      mastery!.familyName,
      mastery!.familyCode,
      1,
      ruleCatalog,
    );
    expect(
      weaponProficiencyService.weaponProficiencyLevels(current.abilities, ruleCatalog).get(mastery!.familyCode),
    ).toBe(1);

    // прокачать до 3 (лестница 1+3+5 = 9 ОР)
    current = characterBuildService.setWeaponMastery(
      current,
      mastery!.masteryRuleCode,
      mastery!.familyName,
      mastery!.familyCode,
      3,
      ruleCatalog,
    );
    expect(
      weaponProficiencyService.weaponProficiencyLevels(current.abilities, ruleCatalog).get(mastery!.familyCode),
    ).toBe(3);

    // снять владение
    current = characterBuildService.setWeaponMastery(
      current,
      mastery!.masteryRuleCode,
      mastery!.familyName,
      mastery!.familyCode,
      0,
      ruleCatalog,
    );
    expect(
      weaponProficiencyService.weaponProficiencyLevels(current.abilities, ruleCatalog).get(mastery!.familyCode) ?? 0,
    ).toBe(0);
  });

  it('понижение до уровня 1 из существующего экземпляра (регрессия: addAbilityInstance no-op)', () => {
    const existing = [{ ruleCode: masteryRule!.code, level: 2, domain: family!.name, domainCode: family!.code }];

    const next = characterBuildService.setWeaponMastery(
      build(existing),
      masteryRule!.code,
      family!.name,
      family!.code,
      1,
      ruleCatalog,
    );

    const instances = next.abilities.filter((a) => a.ruleCode === masteryRule!.code);
    expect(instances).toHaveLength(1);
    expect(instances[0]?.level).toBe(1);
    expect(weaponProficiencyService.weaponProficiencyLevels(next.abilities, ruleCatalog).get(family!.code)).toBe(1);
  });

  it('предмет без семьи и без спекула → null', () => {
    const potion = ruleCatalog.find((r) => r.name === 'Зелье концентрации');
    expect(itemMasteryService.itemMasteryView(potion?.spec as ItemSpec, build().abilities, ruleCatalog)).toBeNull();
    expect(itemMasteryService.itemMasteryView(undefined, build().abilities, ruleCatalog)).toBeNull();
  });
});
