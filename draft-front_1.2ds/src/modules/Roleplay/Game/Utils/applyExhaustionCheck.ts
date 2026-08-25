import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CHECK_EXHAUSTION_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import {
  DECLINE_STATE_CODES,
  DISABLED_STATE_CODE,
  UNCONSCIOUS_STATE_CODE,
  WEAKNESS_STATE_CODE,
  EXHAUSTION_STATE_CODE,
} from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { namedCheckSpec, rollNamedCheck } from '@/modules/Roleplay/Game/Utils/checkRoll';
import { overlayStateTotal } from '@/modules/Roleplay/Game/Utils/applyInjuryCheck';
import { mergeCombatOverlay } from '@/modules/Roleplay/Game/Utils/mergeCombatOverlay';
import { effectiveCharacteristicValues } from '@/modules/Roleplay/Character/Utils/stateRuntimeEffects';
import { checkAdvantageFromStates } from '@/modules/Roleplay/Character/Utils/stateRuntimeEffects';
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
  type ExhaustionChange,
} from '@/modules/Roleplay/Game/Utils/exhaustionCheckMessage';

export interface ApplyExhaustionCheckArgs {
  version: CharacterVersion;
  rng?: DiceRng;
  rules: Rule[];
  mechanics: Mechanic[];
  gameId: number;
  targetKey: CombatEntityKey;
  targetName: string;
  chatId: number | null;
  speaker: ChatSpeaker;
  change: ExhaustionChange;
  sendMessage: (
    content: string,
    attachments: ChatAttachment[],
    chatId: number,
    speaker: ChatSpeaker,
  ) => Promise<boolean>;
}

export interface ApplyExhaustionCheckResult {
  roll: DiceRollResult | null;
  overlay: GameCombatOverlay | null;
  outcome: DeclineOutcome;
  skipped: boolean;
}

function willpowerOf(version: CharacterVersion, rules: Rule[]): DimensionalNumberValue {
  return effectiveCharacteristicValues(version, rules).get('willpower') ?? { base: 1, size: 0 };
}

function hasUnconscious(version: CharacterVersion, rules: Rule[]): boolean {
  const rule = rules.find((item) => item.code === UNCONSCIOUS_STATE_CODE && item.type === 'state');
  if (!rule) return false;

  return version.states.some((state) => state.stateRuleId === rule.id);
}

export async function applyExhaustionCheck(args: ApplyExhaustionCheckArgs): Promise<ApplyExhaustionCheckResult> {
  if (shouldSkipExhaustionCheck(hasUnconscious(args.version, args.rules), args.change)) {
    return { roll: null, overlay: null, outcome: 'unconscious', skipped: true };
  }
  let version = args.version;
  let overlay = await removeStatesByCodes(args.gameId, args.targetKey, version, args.rules, DECLINE_STATE_CODES);
  if (overlay) version = mergeCombatOverlay(version, overlay);

  const exhaustion = overlayStateTotal(version, args.rules, EXHAUSTION_STATE_CODE);
  const adv = checkAdvantageFromStates(version, args.rules);
  const spec = namedCheckSpec(
    `${args.targetName}: Воля (истощение)`,
    willpowerOf(version, args.rules),
    adv,
    args.rules,
    args.targetKey,
  );
  const roll = rollNamedCheck(
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
    overlay = (await addFlagState(args.gameId, args.targetKey, args.rules, code)) ?? overlay;
    if (overlay) version = mergeCombatOverlay(version, overlay);
  }
  const clamped = await clampCombatActionPoints(args.gameId, args.targetKey, version, args.rules);
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
