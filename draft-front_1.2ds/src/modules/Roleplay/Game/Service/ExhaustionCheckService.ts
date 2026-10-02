import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import { EXHAUSTION_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';
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

  private hasUnconscious(version: CharacterVersion, rules: Rule[]): boolean {
    const rule = this.declineRule(rules, 'unconscious');
    if (!rule) return false;

    return version.states.some((state) => state.stateRuleCode === rule.code);
  }

  private declineCodes(rules: Rule[]): string[] {
    const outcomes: DeclineOutcome[] = ['weakness', 'disabled', 'unconscious'];

    return outcomes.flatMap((outcome) => {
      const code = this.declineRule(rules, outcome)?.code;

      return code ? [code] : [];
    });
  }

  private checkCodeOf(rules: Rule[]): string | null {
    const rule = rules.find((item) => item.code === EXHAUSTION_STATE_CODE && item.type === 'state');
    const spec = rule?.spec as StateSpec | undefined;
    const code = spec?.check_code;

    return code ? code : null;
  }

  async applyExhaustionCheck(args: ApplyExhaustionCheckArgs): Promise<ApplyExhaustionCheckResult> {
    if (shouldSkipExhaustionCheck(this.hasUnconscious(args.version, args.rules), args.change)) {
      return { roll: null, overlay: null, outcome: 'unconscious', skipped: true };
    }
    const checkCode = this.checkCodeOf(args.rules);
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
      this.declineCodes(args.rules),
    );

    const exhaustion = injuryCheckService.overlayStateTotal(version, args.rules, EXHAUSTION_STATE_CODE);
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
    const outcome = declineOutcomeFromRating(rating);
    const code = this.declineRule(args.rules, outcome)?.code ?? null;
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
