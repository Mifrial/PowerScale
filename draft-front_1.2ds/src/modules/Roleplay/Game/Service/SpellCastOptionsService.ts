import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { SpellCastPathOption } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastPathOption';
import type { SpellCastSourceOption } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastSourceOption';
import type { SpellCastSpellOption } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastSpellOption';
import { MAGIC_CONTROL_CODE } from '@/modules/Roleplay/Game/Constant/Spell/MAGIC_CONTROL_CODE';
import { MAGIC_CORE_ITEM_CODE } from '@/modules/Roleplay/Game/Constant/Spell/MAGIC_CORE_ITEM_CODE';
import { MAGIC_POWER_CODE } from '@/modules/Roleplay/Game/Constant/Spell/MAGIC_POWER_CODE';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { MagicPathSpec } from '@/modules/Roleplay/Rule/Dto/MagicPath/MagicPathSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { spellCastDifficultyService } from '@/modules/Roleplay/Game/Service/Instance/spellCastDifficultyService';
import { spellDeviationService } from '@/modules/Roleplay/Game/Service/Instance/spellDeviationService';
import type { SpellValue } from '@/modules/Roleplay/Rule/Dto/Ability/SpellValue';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';

/** Источники, пути и изученные заклинания для диалога сотворения. */
export class SpellCastOptionsService {
  listOwnedSpells(overview: CharacterOverview | null, rules: Rule[]): SpellCastSpellOption[] {
    if (!overview) {
      return [];
    }
    const seen = new Set<string>();
    const options: SpellCastSpellOption[] = [];
    for (const ability of overview.abilities) {
      if (ability.type !== 'spell' || seen.has(ability.ruleCode)) {
        continue;
      }
      const rule = rules.find((entry) => entry.code === ability.ruleCode);
      if (!rule || !this.isSpellRule(rule)) {
        continue;
      }
      seen.add(ability.ruleCode);
      const spec = spellCastDifficultyService.asSpellAbilitySpec(rule);
      options.push({
        ruleCode: ability.ruleCode,
        name: ability.name,
        requiredPower: spec ? this.fixedSpellValue(spec.spell.power) : null,
        requiredControl: spec ? this.fixedSpellValue(spec.spell.control) : null,
      });
    }

    return options;
  }

  listSources(version: CharacterVersion | null, rules: Rule[]): SpellCastSourceOption[] {
    if (!version) {
      return [];
    }
    const options: SpellCastSourceOption[] = [];
    const seen = new Set<string>();
    for (const item of version.inventory) {
      if (item.ruleCode !== MAGIC_CORE_ITEM_CODE) {
        continue;
      }
      const key = `inventory:${item.id}`;
      seen.add(MAGIC_CORE_ITEM_CODE);
      const rule = rules.find((entry) => entry.code === item.ruleCode);
      const equipped = item.equipped ? '' : ' (не экипирован)';
      options.push({ key, name: `${rule?.name ?? 'Магическое ядро'}${equipped}` });
    }
    if (!seen.has(MAGIC_CORE_ITEM_CODE)) {
      for (const ability of version.abilities) {
        const rule = rules.find((entry) => entry.code === ability.ruleCode);
        if (!rule || !rule.spec || typeof rule.spec !== 'object') {
          continue;
        }
        for (const levelGrant of this.grantRows(rule.spec)) {
          if (levelGrant.level > ability.level) {
            continue;
          }
          for (const grant of levelGrant.grants) {
            if (grant.type === 'item' && grant.item_code === MAGIC_CORE_ITEM_CODE) {
              const itemRule = rules.find((entry) => entry.code === MAGIC_CORE_ITEM_CODE);
              options.push({
                key: `grant:${ability.ruleCode}`,
                name: itemRule?.name ?? 'Магическое ядро',
              });
              seen.add(MAGIC_CORE_ITEM_CODE);
              break;
            }
          }
        }
        if (seen.has(MAGIC_CORE_ITEM_CODE)) {
          break;
        }
      }
    }

    return options;
  }

  listPaths(version: CharacterVersion | null, rules: Rule[]): SpellCastPathOption[] {
    if (!version) {
      return [];
    }
    const codes = new Set<string>();
    for (const ability of version.abilities) {
      const rule = rules.find((entry) => entry.code === ability.ruleCode);
      if (
        !rule ||
        !['ability', 'trait'].includes(rule.type) ||
        !rule.spec ||
        typeof rule.spec !== 'object' ||
        !('grants' in rule.spec)
      ) {
        continue;
      }
      const rows = this.grantRows(rule.spec);
      for (const levelGrant of rows) {
        if (levelGrant.level > ability.level) {
          continue;
        }
        for (const grant of levelGrant.grants) {
          if (grant.type === 'magic_path' && grant.path_code) {
            codes.add(grant.path_code);
          }
        }
      }
    }
    const options: SpellCastPathOption[] = [];
    for (const pathCode of codes) {
      const rule = rules.find((entry) => entry.code === pathCode && entry.type === 'magic_path');
      const spec = this.asMagicPathSpec(rule);
      options.push({
        pathCode,
        name: rule?.name ?? pathCode,
        checkCode: spec?.check_code ?? null,
      });
    }

    return options;
  }

  characteristicValue(overview: CharacterOverview | null, ruleCode: string): DimensionalNumberValue {
    const found = overview?.characteristics.find((entry) => entry.ruleCode === ruleCode);

    return found?.value ?? { base: 3, size: 0 };
  }

  defaultUsedPower(
    overview: CharacterOverview | null,
    states: CharacterVersion['states'] = [],
    sourceKey = '',
  ): DimensionalNumberValue {
    const base = this.characteristicValue(overview, MAGIC_POWER_CODE);

    return spellDeviationService.penalizeUsedPower(base, spellDeviationService.strengthOnSource(states, sourceKey));
  }

  defaultControl(overview: CharacterOverview | null): DimensionalNumberValue {
    return this.characteristicValue(overview, MAGIC_CONTROL_CODE);
  }

  targetResistanceFromOverview(target: CharacterOverview | null, damageTypeCode: string): number {
    if (!target?.defense) return 0;
    const fromArmor = target.defense.armor.flatMap((armor) =>
      armor.lines
        .filter((line) => line.kind === 'resistance' && line.damageTypeCode === damageTypeCode)
        .map((line) => ({ source_code: line.sourceCode ?? armor.itemRuleCode, delta: line.value })),
    );
    const fromGrants = (target.defense.resistances ?? [])
      .filter((line) => line.kind === 'resistance' && line.damageTypeCode === damageTypeCode)
      .map((line) => ({ source_code: line.sourceCode, delta: line.value }));

    const result = aggregateSourceDeltasService.netSourceDelta([...fromGrants, ...fromArmor]);

    return result;
  }

  private grantRows(spec: object): { level: number; grants: Grant[] }[] {
    if (!('grants' in spec) || !Array.isArray(spec.grants)) {
      return [];
    }

    return spec.grants as { level: number; grants: Grant[] }[];
  }

  private fixedSpellValue(value: SpellValue): DimensionalNumberValue | null {
    if (!('base' in value)) {
      return null;
    }

    return { base: value.base, size: value.size };
  }

  private isSpellRule(rule: Rule): boolean {
    return Boolean(
      rule.type === 'ability' &&
      rule.spec &&
      typeof rule.spec === 'object' &&
      'type' in rule.spec &&
      rule.spec.type === 'spell',
    );
  }

  private asMagicPathSpec(rule: Rule | undefined): MagicPathSpec | null {
    if (!rule || rule.type !== 'magic_path' || !rule.spec || typeof rule.spec !== 'object') {
      return null;
    }
    if (!('type' in rule.spec) || rule.spec.type !== 'magic_path') {
      return null;
    }

    return rule.spec;
  }
}
