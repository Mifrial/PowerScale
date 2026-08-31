import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CHECK_EXHAUSTION_CODE } from '@/modules/Roleplay/Rule/init';
import {
  DECLINE_STATE_CODES,
  DISABLED_STATE_CODE,
  UNCONSCIOUS_STATE_CODE,
  WEAKNESS_STATE_CODE,
  EXHAUSTION_STATE_CODE,
} from '@/modules/Roleplay/Rule/init';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';

import { injuryCheckService } from '@/modules/Roleplay/Game/Service/Instance/injuryCheckService';

import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';

import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/init';
import {
  addFlagState,
  clampCombatActionPoints,
  removeStatesByCodes,
} from '@/modules/Roleplay/Game/Utils/combatStateWrite';
import {
  declineOutcomeFromRating,
  formatExhaustionCheckMessage,
  shouldSkipExhaustionCheck,
} from '@/modules/Roleplay/Game/Utils/exhaustionCheckMessage';

import type { ApplyExhaustionCheckArgs } from '@/modules/Roleplay/Game/Dto/ApplyExhaustionCheckArgs';
import type { ApplyExhaustionCheckResult } from '@/modules/Roleplay/Game/Dto/ApplyExhaustionCheckResult';
export class ExhaustionCheckService {
  constructor(private readonly resolveGameApi: () => IGameApi) {}

  private willpowerOf(version: CharacterVersion, rules: Rule[]): DimensionalNumberValue {
    return (
      stateRuntimeEffectsService.effectiveCharacteristicValues(version, rules).get('willpower') ?? { base: 1, size: 0 }
    );
  }

  private hasUnconscious(version: CharacterVersion, rules: Rule[]): boolean {
    const rule = rules.find((item) => item.code === UNCONSCIOUS_STATE_CODE && item.type === 'state');
    if (!rule) return false;

    return version.states.some((state) => state.stateRuleCode === rule.code);
  }

  async applyExhaustionCheck(args: ApplyExhaustionCheckArgs): Promise<ApplyExhaustionCheckResult> {
    if (shouldSkipExhaustionCheck(this.hasUnconscious(args.version, args.rules), args.change)) {
      return { roll: null, overlay: null, outcome: 'unconscious', skipped: true };
    }
    let version = args.version;
    let overlay = await removeStatesByCodes(
      this.resolveGameApi(),
      args.gameId,
      args.targetKey,
      version,
      args.rules,
      DECLINE_STATE_CODES,
    );
    if (overlay) version = combatOverlayService.mergeCombatOverlay(version, overlay);

    const exhaustion = injuryCheckService.overlayStateTotal(version, args.rules, EXHAUSTION_STATE_CODE);
    const adv = stateRuntimeEffectsService.checkAdvantageFromStates(version, args.rules);
    const spec = checkRollService.namedCheckSpec(
      `${args.targetName}: Воля (истощение)`,
      this.willpowerOf(version, args.rules),
      adv,
      args.rules,
      args.targetKey,
    );
    const roll = checkRollService.rollNamedCheck(
      spec,
      CHECK_EXHAUSTION_CODE,
      { base: exhaustion, size: 0 },
      args.rng ?? Math.random,
      args.rules,
      args.mechanics,
    );
    const rating = roll.check?.rating ?? 0;
    const outcome = declineOutcomeFromRating(rating);
    const code =
      outcome === 'weakness'
        ? WEAKNESS_STATE_CODE
        : outcome === 'disabled'
          ? DISABLED_STATE_CODE
          : outcome === 'unconscious'
            ? UNCONSCIOUS_STATE_CODE
            : null;
    if (code) {
      overlay = (await addFlagState(this.resolveGameApi(), args.gameId, args.targetKey, args.rules, code)) ?? overlay;
      if (overlay) version = combatOverlayService.mergeCombatOverlay(version, overlay);
    }
    const clamped = await clampCombatActionPoints(
      this.resolveGameApi(),
      args.gameId,
      args.targetKey,
      version,
      args.rules,
    );
    if (clamped) overlay = clamped;
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
