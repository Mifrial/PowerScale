import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { EditorCharacteristic } from '@/modules/Roleplay/Character/Dto/Editor/EditorCharacteristic';
import type { EditorWeaponMastery } from '@/modules/Roleplay/Character/Dto/Editor/EditorWeaponMastery';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Character/Constant/CHARACTERISTIC_BASE_RANGE';

export class WeaponProficiencyService {
  /**
   * Лестница семьи оружия для «Владения оружием» (domain_ref 'weapon-family'): стоимость уровней
   * экземпляра и его максимум — из лестницы выбранной семьи (правило weapon_family), а не из
   * заглушки зоны способности. Домен экземпляра — код или имя семьи. Null — семья не найдена.
   */
  weaponFamilyLadder(rules: Rule[], domain: string | null | undefined): number[] | null {
    if (!domain) return null;
    const family = rules.find(
      (rule) => rule.type === 'weapon_family' && (rule.code === domain || rule.name === domain),
    );
    const costs = (family?.spec as { costs?: number[] } | undefined)?.costs ?? [];

    return costs.length > 0 ? [...costs] : null;
  }

  /**
   * Уровни «Владения оружием» по семьям: код/имя семьи → максимальный уровень среди экземпляров.
   * Множественный навык — по записи на экземпляр (домен), уровень агрегируется максимумом.
   */
  weaponProficiencyLevels(
    abilities: Pick<CharacterAbility, 'ruleCode' | 'level' | 'domain' | 'domainCode'>[],
    rules: Rule[],
  ): Map<string, number> {
    const result = new Map<string, number>();
    for (const ability of abilities) {
      const rule = rules.find((entry) => entry.code === ability.ruleCode);
      const spec = rule?.type === 'ability' ? (rule.spec as { domain_ref?: string | null } | undefined) : undefined;
      if (spec?.domain_ref !== 'weapon-family') continue;
      const family = ability.domainCode ?? ability.domain;
      if (!family) continue;
      const current = result.get(family) ?? 0;
      if (ability.level > current) result.set(family, ability.level);
    }

    return result;
  }

  /**
   * Мастерство оружий освоенных семей для стата мастерства: для каждого оружия семьи с нужным
   * профилем (ближний: strike/throw; дальний: shoot) значение = стат мастерства + бонус владения.
   * Бонус в базовый стат не суммируется (R15) — показывается на тайле конкретного оружия.
   */
  weaponMasteryEntries(
    statCode: 'melee-combat' | 'ranged-combat',
    stat: Pick<EditorCharacteristic, 'value'>,
    proficiencyLevels: Map<string, number>,
    rules: Rule[],
  ): EditorWeaponMastery[] {
    const result: EditorWeaponMastery[] = [];
    for (const [family, level] of proficiencyLevels) {
      if (level < 1) continue;
      const familyRule = rules.find(
        (rule) => rule.type === 'weapon_family' && (rule.code === family || rule.name === family),
      );
      if (!familyRule) continue;
      const familyCode = familyRule.code;
      for (const item of rules) {
        if (item.type !== 'item') continue;
        const spec = item.spec as
          { proficiency_family_code?: string | null; weapon?: { weapon_profiles?: { type: string }[] } } | undefined;
        if (spec?.proficiency_family_code !== familyCode) continue;
        const profiles = spec.weapon?.weapon_profiles ?? [];
        // Ближний бой — удары; дальний бой — метание и выстрелы (R14).
        const hasMelee = profiles.some((profile) => profile.type === 'strike');
        const hasRanged = profiles.some((profile) => profile.type === 'throw' || profile.type === 'shoot');
        if (statCode === 'melee-combat' && !hasMelee) continue;
        if (statCode === 'ranged-combat' && !hasRanged) continue;

        const value = new DimensionalNumber(stat.value).modify(level, CHARACTERISTIC_BASE_RANGE).value;
        result.push({
          weaponName: item.name,
          value,
          valueLabel: new DimensionalNumber(value).toString(),
          bonus: level,
        });
      }
    }

    return result;
  }
}
