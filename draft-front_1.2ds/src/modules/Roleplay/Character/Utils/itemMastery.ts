import { weaponProficiencyLevels } from '@/modules/Roleplay/Character/Utils/weaponProficiency';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** Блок «Владение оружием» панели предмета: семья оружия, лестница и текущий уровень прокачки. */
export interface ItemMasteryView {
  /** Правило «Владение оружием» (domain_ref weapon-family). */
  masteryRuleId: string;
  familyName: string;
  familyCode: string;
  /** Лестница стоимости уровней семьи (правило weapon_family). */
  ladder: number[];
  /** Текущий уровень владения семьёй (0 — не обучено). */
  level: number;
  maxLevel: number;
}

/**
 * Данные блока прокачки владения для предмета: семья из proficiency_family_code (правило
 * weapon_family: имя + лестница), уровень — из экземпляров «Владения оружием» в abilities.
 * Null — у предмета нет семьи (или семья/правило владения отсутствуют в ревизии).
 */
export function itemMasteryView(
  spec: ItemSpec | undefined,
  abilities: CharacterAbility[],
  rules: Rule[],
): ItemMasteryView | null {
  const familyCode = spec?.proficiency_family_code;
  if (!familyCode) return null;
  const family = rules.find((rule) => rule.type === 'weapon_family' && rule.code === familyCode);
  const costs = (family?.spec as { costs?: number[] } | undefined)?.costs ?? [];
  if (!family || costs.length === 0) return null;
  const masteryRule = rules.find(
    (rule) =>
      rule.type === 'ability' &&
      (rule.spec as { domain_ref?: string | null } | undefined)?.domain_ref === 'weapon-family',
  );
  if (!masteryRule) return null;

  const level = weaponProficiencyLevels(abilities, rules).get(familyCode) ?? 0;

  return {
    masteryRuleId: masteryRule.id,
    familyName: family.name,
    familyCode,
    ladder: [...costs],
    level,
    maxLevel: costs.length,
  };
}
