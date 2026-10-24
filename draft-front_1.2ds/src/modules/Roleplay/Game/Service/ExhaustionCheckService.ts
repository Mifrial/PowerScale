import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CheckSpec } from '@/modules/Roleplay/Rule/Dto/Check/CheckSpec';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';
import { checkResolutionService, checkSpecService } from '@/modules/Roleplay/Rule/init';
import { concentrationTokenService } from '@/modules/Roleplay/Game/Service/Instance/concentrationTokenService';

import { injuryCheckService } from '@/modules/Roleplay/Game/Service/Instance/injuryCheckService';

import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/init';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import {
  addFlagState,
  clampCombatActionPoints,
  removeStatesByCodes,
} from '@/modules/Roleplay/Game/Utils/combatStateWrite';
import {
  declineOutcomeFromRating,
  formatExhaustionCheckMessage,
  shouldSkipExhaustionCheck,
  type DeclineOutcome,
} from '@/modules/Roleplay/Game/Utils/exhaustionCheckMessage';

import type { ApplyExhaustionCheckArgs } from '@/modules/Roleplay/Game/Dto/ApplyExhaustionCheckArgs';
import type { ApplyExhaustionCheckResult } from '@/modules/Roleplay/Game/Dto/ApplyExhaustionCheckResult';
export class ExhaustionCheckService {
  constructor(private readonly resolveGameApi: () => IGameApi) {}

  private willpowerOf(version: CharacterVersion, rules: Rule[]): DimensionalNumberValue {
    const code = attackDamageService.willpowerRule(rules)?.code;
    if (!code) return { base: 1, size: 0 };

    return stateRuntimeEffectsService.effectiveCharacteristicValues(version, rules).get(code) ?? { base: 1, size: 0 };
  }

  declineRule(rules: Rule[], outcome: DeclineOutcome): Rule | null {
    const flag =
      outcome === 'weakness'
        ? 'decline_weakness'
        : outcome === 'disabled'
          ? 'decline_disabled'
          : outcome === 'unconscious'
            ? 'decline_unconscious'
            : null;
    if (!flag) return null;

    return (
      rules.find((candidate) => {
        const spec = candidate.spec as StateSpec | undefined;

        return candidate.type === 'state' && spec?.[flag] === true;
      }) ?? null
    );
  }

  /** Исход проверки: сначала outcomes из спеки, для старых ревизий — флаги decline_*. */
  outcomeForRating(
    check: CheckSpec | null,
    rules: Rule[],
    rating: number,
  ): { outcome: DeclineOutcome; code: string | null } {
    const outcomes = check?.outcomes ?? [];
    if (outcomes.length > 0) {
      const matched = checkSpecService.matchOutcome(outcomes, rating);
      const code = matched?.outcome.state_rule_code ?? null;
      if (matched === null || code === null) return { outcome: 'clear', code: null };

      return {
        outcome: this.outcomeKind(checkSpecService.outcomeRank(outcomes, matched.index)),
        code,
      };
    }

    const outcome = declineOutcomeFromRating(rating);

    return { outcome, code: this.declineRule(rules, outcome)?.code ?? null };
  }

  /** Коды состояний, которые снимает проверка истощения. */
  declineCodes(rules: Rule[], check: CheckSpec | null): string[] {
    const outcomeCodes = checkSpecService.outcomeStateCodes(check?.outcomes);
    if (outcomeCodes.length > 0) return outcomeCodes;

    return rules
      .filter((candidate) => {
        const spec = candidate.spec as StateSpec | undefined;

        return candidate.type === 'state' && spec !== undefined && this.declineRank(spec) !== null;
      })
      .map((candidate) => candidate.code);
  }

  private hasUnconscious(version: CharacterVersion, rules: Rule[], check: CheckSpec | null): boolean {
    const codes = this.unconsciousCodes(rules, check);

    return version.states.some((state) => codes.has(state.stateRuleCode));
  }

  private unconsciousCodes(rules: Rule[], check: CheckSpec | null): Set<string> {
    const flagged = new Set(
      rules
        .filter((candidate) => {
          const spec = candidate.spec as StateSpec | undefined;

          return candidate.type === 'state' && spec?.decline_unconscious === true;
        })
        .map((candidate) => candidate.code),
    );
    const inOutcomes = checkSpecService.outcomeStateCodes(check?.outcomes).filter((code) => flagged.has(code));
    if (inOutcomes.length > 0) return new Set(inOutcomes);

    return flagged;
  }

  private checkCodeOf(rules: Rule[]): string | null {
    const rule = attackDamageService.exhaustionRule(rules);
    const spec = rule?.spec as StateSpec | undefined;
    const code = spec?.check_code;

    return code ? code : null;
  }

  private checkSpecOf(rules: Rule[], code: string): CheckSpec | null {
    return checkResolutionService.asCheckSpec(rules.find((item) => item.code === code && item.type === 'check'));
  }

  private declineRank(spec: StateSpec): number | null {
    const flags = [spec.decline_weakness, spec.decline_disabled, spec.decline_unconscious].filter(
      (flag) => flag === true,
    );
    if (flags.length !== 1) return null;
    if (spec.decline_weakness === true) return 1;
    if (spec.decline_disabled === true) return 2;

    return 3;
  }

  private outcomeKind(rank: number): DeclineOutcome {
    if (rank === 1) return 'weakness';
    if (rank === 2) return 'disabled';
    if (rank === 3) return 'unconscious';

    return 'clear';
  }

  async applyExhaustionCheck(args: ApplyExhaustionCheckArgs): Promise<ApplyExhaustionCheckResult> {
    const checkCode = this.checkCodeOf(args.rules);
    const check = checkCode ? this.checkSpecOf(args.rules, checkCode) : null;
    if (shouldSkipExhaustionCheck(this.hasUnconscious(args.version, args.rules, check), args.change)) {
      return { roll: null, overlay: null, outcome: 'unconscious', skipped: true };
    }
    if (!checkCode) {
      return { roll: null, overlay: args.overlay ?? null, outcome: 'clear', skipped: true };
    }
    const version = args.version;
    let overlay = args.overlay ?? null;
    await removeStatesByCodes(
      this.resolveGameApi(),
      args.gameId,
      args.targetKey,
      version,
      args.rules,
      this.declineCodes(args.rules, check),
    );

    const exhaustionRule = attackDamageService.exhaustionRule(args.rules);
    if (!exhaustionRule) {
      return { roll: null, overlay: args.overlay ?? null, outcome: 'clear', skipped: true };
    }
    const exhaustion = injuryCheckService.overlayStateTotal(version, args.rules, exhaustionRule.code);
    const adv = stateRuntimeEffectsService.checkAdvantageFromStates(version, args.rules);
    let spent = 0;
    const maxSpend = concentrationTokenService.maxSpend(version, overlay, args.rules, checkCode);
    if (maxSpend > 0 && args.askTokenSpend) {
      spent = Math.min(
        maxSpend,
        concentrationTokenService.parseSpendAmount(
          await args.askTokenSpend({
            maxSpend,
            remaining: concentrationTokenService.tokenCurrent(version, overlay, args.rules),
          }),
        ),
      );
      if (spent > 0) {
        overlay = await concentrationTokenService.spendToken(
          this.resolveGameApi(),
          args.gameId,
          args.targetKey,
          version,
          overlay,
          args.rules,
          spent,
        );
      }
    }
    const spec = checkRollService.namedCheckSpec(
      `${args.targetName}: Воля (истощение)`,
      this.willpowerOf(version, args.rules),
      adv,
      args.rules,
      args.targetKey,
    );
    if (spent > 0) {
      spec.advantages = spec.advantages.concat(concentrationTokenService.tokenAdvantage(spent));
    }
    const roll = checkRollService.rollNamedCheck(
      spec,
      checkCode,
      { base: exhaustion, size: 0 },
      args.rng ?? Math.random,
      args.rules,
      args.mechanics,
    );
    const rating = roll.check?.rating ?? 0;
    const { outcome, code } = this.outcomeForRating(check, args.rules, rating);
    if (code) {
      await addFlagState(this.resolveGameApi(), args.gameId, args.targetKey, args.rules, code);
    }
    await clampCombatActionPoints(this.resolveGameApi(), args.gameId, args.targetKey, version, args.rules);
    if (args.chatId !== null) {
      const sentRoll = await args.sendMessage(
        '',
        [{ type: ROLL_ATTACHMENT_TYPE, payload: roll }],
        args.chatId,
        args.speaker,
      );
      if (!sentRoll) throw new Error('Не удалось отправить бросок истощения');
      const sent = await args.sendMessage(
        formatExhaustionCheckMessage(args.targetName, rating, outcome, args.targetKey),
        [],
        args.chatId,
        args.speaker,
      );
      if (!sent) throw new Error('Не удалось отправить проверку на истощение');
    }

    return { roll, overlay, outcome, skipped: false };
  }
}
