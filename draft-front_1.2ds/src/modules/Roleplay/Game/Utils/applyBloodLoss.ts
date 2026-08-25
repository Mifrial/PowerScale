import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import {
  BLOOD_LOSS_STATE_CODE,
  EXHAUSTION_STATE_CODE,
  WOUND_STATE_CODE,
} from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { overlayStateTotal } from '@/modules/Roleplay/Game/Utils/applyInjuryCheck';
import { applyInjuryCheck } from '@/modules/Roleplay/Game/Utils/applyInjuryCheck';
import { applyExhaustionCheck } from '@/modules/Roleplay/Game/Utils/applyExhaustionCheck';
import { injuryDifficulty } from '@/modules/Roleplay/Game/Utils/injuryRoll';
import { resolveInjuryProcedure } from '@/modules/Roleplay/Game/Utils/resolveInjuryProcedure';
import { mergeCombatOverlay } from '@/modules/Roleplay/Game/Utils/mergeCombatOverlay';
import { formatBloodLossTickMessage } from '@/modules/Roleplay/Game/Utils/bloodLossMessage';
import { setNumericState } from '@/modules/Roleplay/Game/Utils/combatStateWrite';
import {
  applyBloodLossGain,
  bloodLossInjuryDifficulty,
  reservedExhaustion,
} from '@/modules/Roleplay/Game/Utils/bloodLossMath';

export { applyBloodLossGain, bloodLossInjuryDifficulty, reservedExhaustion };

export interface ApplyBloodLossArgs {
  version: CharacterVersion;
  delta: number;
  endurance: number;
  rng?: DiceRng;
  rules: Rule[];
  mechanics: Mechanic[];
  gameId: number;
  targetKey: CombatEntityKey;
  targetName: string;
  chatId: number | null;
  speaker: ChatSpeaker;
  sendMessage: (
    content: string,
    attachments: ChatAttachment[],
    chatId: number,
    speaker: ChatSpeaker,
  ) => Promise<boolean>;
}

export async function applyBloodLossTick(args: ApplyBloodLossArgs): Promise<GameCombatOverlay | null> {
  if (args.delta <= 0) return null;
  let version = args.version;
  const oldBlood = overlayStateTotal(version, args.rules, BLOOD_LOSS_STATE_CODE);
  const oldExh = overlayStateTotal(version, args.rules, EXHAUSTION_STATE_CODE);
  const next = applyBloodLossGain(oldBlood, args.delta, oldExh);
  let overlay = await setNumericState(
    args.gameId,
    args.targetKey,
    version,
    args.rules,
    BLOOD_LOSS_STATE_CODE,
    next.bloodLoss,
  );
  if (overlay) version = mergeCombatOverlay(version, overlay);
  if (args.chatId !== null) {
    const sent = await args.sendMessage(
      formatBloodLossTickMessage(args.targetName, args.delta, next.bloodLoss, args.targetKey),
      [],
      args.chatId,
      args.speaker,
    );
    if (!sent) throw new Error('Не удалось отправить сообщение о кровопотере');
  }
  if (next.exhaustion !== oldExh) {
    overlay =
      (await setNumericState(
        args.gameId,
        args.targetKey,
        version,
        args.rules,
        EXHAUSTION_STATE_CODE,
        next.exhaustion,
      )) ?? overlay;
    if (overlay) version = mergeCombatOverlay(version, overlay);
  }
  if (next.addedExhaustion > 0) {
    const checked = await applyExhaustionCheck({
      version,
      rng: args.rng,
      rules: args.rules,
      mechanics: args.mechanics,
      gameId: args.gameId,
      targetKey: args.targetKey,
      targetName: args.targetName,
      chatId: args.chatId,
      speaker: args.speaker,
      change: 'increase',
      sendMessage: args.sendMessage,
    });
    if (checked.overlay) {
      overlay = checked.overlay;
      version = mergeCombatOverlay(version, checked.overlay);
    }
  }
  const bloodDc = bloodLossInjuryDifficulty(next.reserved);
  const procedure = resolveInjuryProcedure(args.rules, args.mechanics);
  const normalDc = injuryDifficulty(
    {
      leftoverDamage: 0,
      woundStrength: 0,
      endurance: Math.max(1, args.endurance),
      exhaustion: next.exhaustion,
      attackSr: 0,
    },
    procedure,
    0,
  );
  const useBlood = bloodDc != null;
  const difficulty = useBlood ? bloodDc : normalDc;
  if (difficulty > 0 && (useBlood || next.addedExhaustion > 0)) {
    const applied = await applyInjuryCheck({
      input: {
        leftoverDamage: 0,
        woundStrength: 0,
        difficulty,
        endurance: Math.max(1, args.endurance),
        exhaustion: next.exhaustion,
        attackSr: 0,
        actorKey: args.targetKey,
        label: useBlood ? 'Проверка на увечье (кровопотеря)' : undefined,
      },
      rng: args.rng,
      rules: args.rules,
      mechanics: args.mechanics,
      gameId: args.gameId,
      targetKey: args.targetKey,
      targetName: args.targetName,
      chatId: args.chatId,
      speaker: args.speaker,
      skipIfNoRoll: !useBlood,
      chatPrefix: useBlood ? 'Увечье от кровопотери.' : undefined,
      targetVersion: version,
      sendMessage: args.sendMessage,
    });
    if (applied.overlay) overlay = applied.overlay;
  }

  return overlay;
}

export async function applyTurnWoundBleed(args: Omit<ApplyBloodLossArgs, 'delta'>): Promise<GameCombatOverlay | null> {
  const delta = overlayStateTotal(args.version, args.rules, WOUND_STATE_CODE);

  return applyBloodLossTick({ ...args, delta });
}
