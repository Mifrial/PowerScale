import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { InjuryRollInput } from '@/modules/Roleplay/Game/Dto/InjuryRollInput';
import type { StrikeUpgradeOption } from '@/modules/Roleplay/Game/Dto/Strike/StrikeUpgradeOption';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { StrikeUpgrade } from '@/modules/Roleplay/Rule/Dto/Ability/StrikeUpgrade';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { knowledgeCheckService } from '@/modules/Roleplay/Character/init';

/**
 * Какие `strike_upgrade` можно отметить на запуске удара и как они входят в проверку увечья.
 */
export class StrikeUpgradeService {
  optionIdOf(ruleCode: string, modeCode: string): string {
    return `${ruleCode}:${modeCode}`;
  }

  listApplicable(
    abilities: CharacterAbility[],
    target: CharacterVersion | null | undefined,
    rules: Rule[],
  ): StrikeUpgradeOption[] {
    const options: StrikeUpgradeOption[] = [];
    const seen = new Set<string>();
    for (const ability of abilities) {
      if (ability.level < 1) continue;
      const rule = rules.find((entry) => entry.code === ability.ruleCode);
      const spec = this.upgradeSpec(rule);
      if (!rule || !spec) continue;
      if (spec.requires_physiology && !this.hasPhysiology(abilities, target, rules)) continue;
      for (const mode of spec.modes) {
        const optionId = this.optionIdOf(rule.code, mode.code);
        if (seen.has(optionId)) continue;
        seen.add(optionId);
        options.push({
          optionId,
          ruleCode: rule.code,
          name: rule.name,
          exclusiveGroup: spec.exclusive_group,
          mode,
        });
      }
    }

    return options;
  }

  listApplicableUnion(
    abilities: CharacterAbility[],
    targets: (CharacterVersion | null | undefined)[],
    rules: Rule[],
  ): StrikeUpgradeOption[] {
    if (targets.length === 0) return [];
    const byId = new Map<string, StrikeUpgradeOption>();
    for (const target of targets) {
      for (const option of this.listApplicable(abilities, target, rules)) {
        byId.set(option.optionId, option);
      }
    }

    return [...byId.values()];
  }

  selectedOf(applicable: StrikeUpgradeOption[], codes: string[]): StrikeUpgradeOption[] {
    const chosen = new Set(codes);

    return applicable.filter((option) => chosen.has(option.optionId));
  }

  pruneSelected(applicable: StrikeUpgradeOption[], codes: string[]): string[] {
    const allowed = new Map(applicable.map((option) => [option.optionId, option]));
    const kept: string[] = [];
    const usedGroups = new Set<string>();
    for (const code of codes) {
      const option = allowed.get(code);
      if (!option) continue;
      if (usedGroups.has(option.exclusiveGroup)) continue;
      usedGroups.add(option.exclusiveGroup);
      kept.push(code);
    }

    return kept;
  }

  injuryAdvantageModifiers(selected: StrikeUpgradeOption[]): AdvantageModifier[] {
    return selected
      .filter((option) => option.mode.injury_check_advantage !== 0)
      .map((option) => ({
        source_code: option.optionId,
        source_label: `${option.name} · ${option.mode.label}`,
        delta: option.mode.injury_check_advantage,
      }));
  }

  withInjuryAdvantages(
    input: InjuryRollInput,
    abilities: CharacterAbility[],
    target: CharacterVersion | null | undefined,
    codes: string[],
    rules: Rule[],
  ): InjuryRollInput {
    const applicable = this.listApplicable(abilities, target, rules);
    const selected = this.selectedOf(applicable, this.pruneSelected(applicable, codes));
    const extras = this.injuryAdvantageModifiers(selected);
    if (!extras.length) return input;

    return { ...input, advantages: [...(input.advantages ?? []), ...extras] };
  }

  private hasPhysiology(
    abilities: CharacterAbility[],
    target: CharacterVersion | null | undefined,
    rules: Rule[],
  ): boolean {
    const speciesCode = target?.raceRuleCode ?? null;
    if (!speciesCode) return false;

    return (
      knowledgeCheckService.effectiveLevel(
        abilities,
        'physiology',
        { species: { code: speciesCode, text: '' } },
        rules,
      ) >= 1
    );
  }

  private upgradeSpec(rule: Rule | undefined): StrikeUpgrade | null {
    if (!rule || rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object') return null;
    if (!('strike_upgrade' in rule.spec) || !rule.spec.strike_upgrade) return null;

    return rule.spec.strike_upgrade;
  }
}
