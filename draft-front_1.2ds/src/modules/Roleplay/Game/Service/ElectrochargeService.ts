import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import { keywordExperienceService } from '@/modules/Roleplay/Character/init';
import type { SpellCastSpellOption } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastSpellOption';
import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';
import { ACTION_POINTS_CODE } from '@/modules/Roleplay/Game/Constant/Combat/ACTION_POINTS_CODE';
import { actionOdCost } from '@/modules/Roleplay/Game/Utils/combatActions';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { SpellChargeSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SpellChargeSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { spellCastDifficultyService } from '@/modules/Roleplay/Game/Service/Instance/spellCastDifficultyService';

/** Кап, выдача, трата и фильтр каста электрозаряда без Vue. */
export class ElectrochargeService {
  chargeSpec(spellCode: string, rules: Rule[]): SpellChargeSpec | null {
    const spec = spellCastDifficultyService.asSpellAbilitySpec(rules.find((rule) => rule.code === spellCode));

    return spec?.spell.charge ?? null;
  }

  cap(spellCode: string, abilities: CharacterAbility[], rules: Rule[], keywords: Keyword[]): number {
    const charge = this.chargeSpec(spellCode, rules);
    if (!charge) {
      return 0;
    }
    const upgrade = this.learnedCapUpgrade(spellCode, abilities, rules);
    if (!upgrade) {
      return charge.default_cap;
    }
    const experience = keywordExperienceService.experienceOf(
      upgrade.experience_keyword_code,
      abilities,
      rules,
      keywords,
      (ability, _rule, spec) => keywordExperienceService.zoneLadderCost(ability, spec),
    );
    const steps = [...upgrade.steps].sort((left, right) => right.min_experience - left.min_experience);
    for (const step of steps) {
      if (experience < step.min_experience) {
        continue;
      }
      if (step.per_experience && step.per_experience > 0) {
        return Math.floor(experience / step.per_experience);
      }
      if (step.cap != null) {
        return step.cap;
      }
    }

    return upgrade.base;
  }

  grant(states: CharacterStateValue[], sustainId: string, spec: SpellChargeSpec, cap: number): CharacterStateValue {
    const amount = Math.max(0, spec.grant);
    const current = states.find((state) => this.isBound(state, spec.state_code, sustainId));
    const nextValue = Math.min(cap, (current?.value ?? 0) + amount);

    return {
      stateRuleCode: spec.state_code,
      value: nextValue,
      boundSustainId: sustainId,
    };
  }

  spend(states: CharacterStateValue[], sustainId: string, spec: SpellChargeSpec): CharacterStateValue | null {
    const current = states.find((state) => this.isBound(state, spec.state_code, sustainId));
    if (!current || (current.value ?? 0) < spec.spend.amount) {
      return null;
    }

    return {
      ...current,
      value: (current.value ?? 0) - spec.spend.amount,
    };
  }

  boundIndex(states: CharacterStateValue[], stateCode: string, sustainId: string): number {
    return states.findIndex((state) => this.isBound(state, stateCode, sustainId));
  }

  boundIndices(states: CharacterStateValue[], sustainId: string): number[] {
    return states
      .map((state, index) => (state.boundSustainId === sustainId ? index : -1))
      .filter((index) => index >= 0)
      .reverse();
  }

  canOpenCast(state: CharacterStateValue, spells: ActiveSpell[], rules: Rule[]): boolean {
    if (!state.boundSustainId || (state.value ?? 0) < 1) {
      return false;
    }
    const sustain = spells.find((spell) => spell.id === state.boundSustainId);
    if (!sustain) {
      return false;
    }
    const spec = this.chargeSpec(sustain.spellCode, rules);

    return Boolean(spec && spec.state_code === state.stateRuleCode && (state.value ?? 0) >= spec.spend.amount);
  }

  filterCastOptions(
    owned: SpellCastSpellOption[],
    rules: Rule[],
    keywords: Keyword[],
    turnApMax: number,
    spec: SpellChargeSpec,
  ): SpellCastSpellOption[] {
    const keywordId = keywords.find((keyword) => keyword.code === spec.spend.keyword_code)?.id;
    if (keywordId === undefined) {
      return [];
    }

    return owned.filter((option) => {
      const rule = rules.find((entry) => entry.code === option.ruleCode);
      const ability = spellCastDifficultyService.asSpellAbilitySpec(rule);
      if (!rule || !ability) {
        return false;
      }
      if (!(rule.keywordIds ?? []).includes(keywordId)) {
        return false;
      }
      const duration = ability.spell.duration.type;
      if (spec.spend.exclude_duration_types.includes(duration)) {
        return false;
      }
      if (spec.spend.creation_max === 'turn_ap' && this.creationCost(ability) > turnApMax) {
        return false;
      }

      return true;
    });
  }

  creationCost(spec: Extract<AbilitySpec, { type: 'spell' }>): number {
    return actionOdCost(spec.action_components);
  }

  turnApMax(overview: CharacterOverview | null): number {
    const resource = overview?.resources.find((entry) => entry.ruleCode === ACTION_POINTS_CODE);

    return resource ? DimensionalNumber.from(resource.max).toNumber() : 0;
  }

  private isBound(state: CharacterStateValue, stateCode: string, sustainId: string): boolean {
    return state.stateRuleCode === stateCode && state.boundSustainId === sustainId;
  }

  private learnedCapUpgrade(spellCode: string, abilities: CharacterAbility[], rules: Rule[]) {
    for (const ability of abilities) {
      if (ability.level < 1) {
        continue;
      }
      const rule = rules.find((entry) => entry.code === ability.ruleCode);
      if (!rule || rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object') {
        continue;
      }
      if (!('spell_upgrade' in rule.spec) || !rule.spec.spell_upgrade?.charge_cap) {
        continue;
      }
      if (!('parent_ability_code' in rule.spec) || rule.spec.parent_ability_code !== spellCode) {
        continue;
      }

      return rule.spec.spell_upgrade.charge_cap;
    }

    return null;
  }
}
