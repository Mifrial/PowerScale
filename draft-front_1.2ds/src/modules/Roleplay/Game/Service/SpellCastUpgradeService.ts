import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { GamePhpSpellCastUpgrade } from '@/modules/Roleplay/Game/Dto/GamePhpSpellCastUpgrade';
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
      if (!this.matches(spec, ability, pathCode, spellCode)) {
        continue;
      }
      if (
        !spec.spell_upgrade.action_point_delta &&
        !spec.spell_upgrade.check_advantage &&
        !spec.spell_upgrade.resistance_penetration_per_step &&
        !spec.spell_upgrade.chain &&
        !spec.spell_upgrade.charge_cap
      ) {
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

  /** Улучшения, которые понимает серверный runtime и можно отправить в real-каст. */
  listRuntimeApplicable(
    abilities: CharacterAbility[],
    pathCode: string | null,
    spellCode: string,
    rules: Rule[],
  ): SpellCastUpgradeOption[] {
    return this.listApplicable(abilities, pathCode, spellCode, rules).filter((option) =>
      this.isRuntimeSupported(option),
    );
  }

  /** Runtime M2 исполняет скалярные примитивы и не умеет цепь и заряды. */
  isRuntimeSupported(option: SpellCastUpgradeOption): boolean {
    return !option.upgrade.chain && !option.upgrade.charge_cap;
  }

  /** Запрос улучшений: value уходит только для улучшения с шагами пробития. */
  requestOf(selected: SpellCastUpgradeOption[], values: Readonly<Record<string, number>>): GamePhpSpellCastUpgrade[] {
    return selected.map((option) => {
      if (!option.upgrade.resistance_penetration_per_step) {
        return { code: option.ruleCode };
      }

      return { code: option.ruleCode, value: this.parameterValue(option, values) };
    });
  }

  selectedOf(applicable: SpellCastUpgradeOption[], codes: string[]): SpellCastUpgradeOption[] {
    const chosen = new Set(codes);

    return applicable.filter((option) => chosen.has(option.ruleCode));
  }

  pruneSelected(applicable: SpellCastUpgradeOption[], codes: string[]): string[] {
    const allowed = new Set(applicable.map((option) => option.ruleCode));

    return codes.filter((code) => allowed.has(code));
  }

  chipLabel(option: SpellCastUpgradeOption, parameterValue = 0): string {
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
    if (option.upgrade.resistance_penetration_per_step) {
      bits.push(`х = ${parameterValue}`);
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

  requiredPowerDelta(selected: SpellCastUpgradeOption[], values: Readonly<Record<string, number>>): number {
    return selected.reduce((sum, option) => sum + this.parameterValue(option, values), 0);
  }

  resistancePenetration(selected: SpellCastUpgradeOption[], values: Readonly<Record<string, number>>): number {
    return selected.reduce(
      (sum, option) =>
        sum + this.parameterValue(option, values) * (option.upgrade.resistance_penetration_per_step ?? 0),
      0,
    );
  }

  private parameterValue(option: SpellCastUpgradeOption, values: Readonly<Record<string, number>>): number {
    if (!option.upgrade.resistance_penetration_per_step) {
      return 0;
    }

    return Math.max(0, Math.floor(values[option.ruleCode] ?? 1));
  }

  /** Привязка улучшения: конкретное заклинание либо экземпляр домена. */
  private matches(
    spec: { parent_ability_code: string | null; spell_upgrade: SpellUpgrade },
    ability: CharacterAbility,
    pathCode: string | null,
    spellCode: string,
  ): boolean {
    const parent = spec.parent_ability_code;
    if (parent) {
      if (parent !== spellCode) {
        return false;
      }

      return spec.spell_upgrade.any_path === true || !ability.domainCode || ability.domainCode === pathCode;
    }

    if (!pathCode || !ability.domainCode) {
      return false;
    }

    return spec.spell_upgrade.any_path === true || ability.domainCode === pathCode;
  }

  private upgradeSpec(rule: Rule | undefined): {
    parent_ability_code: string | null;
    spell_upgrade: SpellUpgrade;
  } | null {
    if (!rule || rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object') {
      return null;
    }
    if (!('spell_upgrade' in rule.spec) || !rule.spec.spell_upgrade) {
      return null;
    }
    const parent = 'parent_ability_code' in rule.spec ? rule.spec.parent_ability_code : null;

    return {
      parent_ability_code: parent ?? null,
      spell_upgrade: rule.spec.spell_upgrade,
    };
  }
}
