import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { SpellCastUpgradeOption } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastUpgradeOption';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { SpellUpgrade } from '@/modules/Roleplay/Rule/Dto/Ability/SpellUpgrade';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** Какие `spell_upgrade` можно отметить на этот каст. */
export class SpellCastUpgradeService {
  listApplicable(
    abilities: CharacterAbility[],
    pathCode: string | null,
    spellCode: string,
    rules: Rule[],
  ): SpellCastUpgradeOption[] {
    const options: SpellCastUpgradeOption[] = [];
    const seen = new Set<string>();
    for (const ability of abilities) {
      const rule = rules.find((entry) => entry.code === ability.ruleCode);
      const spec = this.upgradeSpec(rule);
      if (!rule || !spec) {
        continue;
      }
      const parent = spec.parent_ability_code;
      const matchesParent = parent === spellCode;
      const matchesPath =
        !parent && spec.domain_ref === 'magic-path' && Boolean(pathCode) && ability.domainCode === pathCode;
      if (!matchesParent && !matchesPath) {
        continue;
      }
      if (!spec.spell_upgrade.action_point_delta && !spec.spell_upgrade.check_advantage && !spec.spell_upgrade.chain) {
        continue;
      }
      if (seen.has(rule.code)) {
        continue;
      }
      seen.add(rule.code);
      options.push({ ruleCode: rule.code, name: rule.name, upgrade: spec.spell_upgrade });
    }

    return options;
  }

  selectedOf(applicable: SpellCastUpgradeOption[], codes: string[]): SpellCastUpgradeOption[] {
    const chosen = new Set(codes);

    return applicable.filter((option) => chosen.has(option.ruleCode));
  }

  pruneSelected(applicable: SpellCastUpgradeOption[], codes: string[]): string[] {
    const allowed = new Set(applicable.map((option) => option.ruleCode));

    return codes.filter((code) => allowed.has(code));
  }

  chipLabel(option: SpellCastUpgradeOption): string {
    const bits: string[] = [];
    if (option.upgrade.action_point_delta) {
      bits.push(`+${option.upgrade.action_point_delta} ОД`);
    }
    if (option.upgrade.check_advantage) {
      bits.push('преимущество');
    }
    if (option.upgrade.chain) {
      bits.push('цепь');
    }
    if (bits.length === 0) {
      return option.name;
    }

    return `${option.name} · ${bits.join(', ')}`;
  }

  actionPointDelta(selected: SpellCastUpgradeOption[]): number {
    return selected.reduce((sum, option) => sum + option.upgrade.action_point_delta, 0);
  }

  advantageModifiers(selected: SpellCastUpgradeOption[]): AdvantageModifier[] {
    return selected
      .filter((option) => option.upgrade.check_advantage)
      .map((option) => ({
        source_code: option.ruleCode,
        source_label: option.name,
        delta: option.upgrade.check_advantage ?? 0,
      }));
  }

  private upgradeSpec(rule: Rule | undefined): {
    parent_ability_code: string | null;
    domain_ref?: string | null;
    spell_upgrade: SpellUpgrade;
  } | null {
    if (!rule || rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object') {
      return null;
    }
    if (!('spell_upgrade' in rule.spec) || !rule.spec.spell_upgrade) {
      return null;
    }
    const parent = 'parent_ability_code' in rule.spec ? rule.spec.parent_ability_code : null;
    const domainRef = 'domain_ref' in rule.spec ? rule.spec.domain_ref : null;

    return {
      parent_ability_code: parent ?? null,
      domain_ref: domainRef,
      spell_upgrade: rule.spec.spell_upgrade,
    };
  }
}
