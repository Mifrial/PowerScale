import { weaponProficiencyService } from '@/modules/Roleplay/Character/Service/Instance/weaponProficiencyService';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { ItemMasteryView } from '@/modules/Roleplay/Character/Dto/ItemMasteryView';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

export class ItemMasteryService {
  /**
   * Данные блока прокачки владения для предмета: семья из proficiency_family_code (правило
   * weapon_family: имя + лестница), уровень — из экземпляров «Владения оружием» в abilities.
   * Null — у предмета нет семьи (или семья/правило владения отсутствуют в ревизии).
   */
  itemMasteryView(spec: ItemSpec | undefined, abilities: CharacterAbility[], rules: Rule[]): ItemMasteryView | null {
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

    const level = weaponProficiencyService.weaponProficiencyLevels(abilities, rules).get(familyCode) ?? 0;

    return {
      masteryRuleCode: masteryRule.code,
      familyName: family.name,
      familyCode,
      ladder: [...costs],
      level,
      maxLevel: costs.length,
    };
  }
}
