import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CurrentSpeed } from '@/modules/Roleplay/Game/Dto/CurrentSpeed';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import {
  actionOdCost,
  turnResourceCode,
  actionUsesChosenCost,
  asActionAbilitySpec,
  asProcessAbilitySpec,
} from '@/modules/Roleplay/Game/Utils/combatActions';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { woundActionLaunchService } from '@/modules/Roleplay/Game/Service/Instance/woundActionLaunchService';
import { characterOverviewService } from '@/modules/Roleplay/Character/init';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import { KEYWORD_REACTION } from '@/modules/Roleplay/Keyword/Constant/KEYWORD_REACTION';

/** Список действий диалога запуска: требования, владение и цена ОД. */
export class ActionLaunchCatalogService {
  listOptions(input: {
    rules: Rule[];
    currentSpeed: CurrentSpeed;
    actorVersion: CharacterVersion | null;
    woundOverlay: GameCombatOverlay | null;
    keywords: Keyword[];
  }): CombatActionOption[] {
    const overview = input.actorVersion ? characterOverviewService.build(input.actorVersion, input.rules) : null;
    const owned = new Set(overview?.abilities.map((ability) => ability.ruleCode) ?? []);
    const reactionIds = new Set(
      input.keywords.filter((keyword) => keyword.code === KEYWORD_REACTION).map((keyword) => keyword.id),
    );

    return input.rules.flatMap((rule) => {
      const spec = asActionAbilitySpec(rule);
      const process = asProcessAbilitySpec(rule);
      const requirements =
        rule.spec && typeof rule.spec === 'object' && 'requirements' in rule.spec ? rule.spec.requirements : [];
      // 71 — «Рука»: безоружные удары идут своим путём, не через каталог действий.
      if (rule.keywordIds?.includes(71)) return [];
      if (!spec && !process) return [];
      if (spec && !this.requirementsSatisfied(spec.requirements, input.currentSpeed)) return [];
      if (process && !this.requirementsSatisfied(requirements, input.currentSpeed)) return [];
      if (
        !owned.has(rule.code) &&
        (!spec || !Object.values(spec.zones ?? {}).some((zone) => zone?.kind === 'automatic'))
      )
        return [];
      const odCost = woundActionLaunchService.isBandage(rule.code)
        ? woundActionLaunchService.bandageOd(input.actorVersion, input.woundOverlay)
        : spec
          ? actionOdCost(spec.action_components, 0, turnResourceCode(input.rules))
          : 0;
      const option: CombatActionOption = {
        ruleCode: rule.code,
        code: rule.code,
        name: rule.name,
        odCost,
        isVariableCost: spec ? actionUsesChosenCost(spec.action_components) : false,
        effects: spec ? actionEffectService.effectsOf(rule) : [],
        isAttack: false,
        isReaction: rule.keywordIds?.some((keywordId) => reactionIds.has(keywordId)) ?? false,
        isProcess: process !== null,
        process: process ?? undefined,
        operations: spec?.operations,
        combatAction: spec?.combat_action,
      };

      return [option];
    });
  }

  private requirementsSatisfied(
    entries: { level: number; requirements: Requirement[] }[],
    currentSpeed: CurrentSpeed,
  ): boolean {
    return entries.every((entry) =>
      entry.requirements.every((requirement) => {
        if (requirement.type === 'current_speed') {
          const component = currentSpeed[requirement.axis];

          return (
            component.direction === requirement.direction &&
            component.stepsPerActionPoint >= requirement.min_steps_per_action_point
          );
        }
        if (requirement.type === 'and')
          return this.requirementsSatisfied([{ level: 1, requirements: requirement.children }], currentSpeed);
        if (requirement.type === 'or')
          return requirement.children.some((child) =>
            this.requirementsSatisfied([{ level: 1, requirements: [child] }], currentSpeed),
          );

        return true;
      }),
    );
  }
}
