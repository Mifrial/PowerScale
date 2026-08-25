import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { InjuryRollInput } from '@/modules/Roleplay/Game/Utils/injuryRoll';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { INJURY_DETAILS_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Injury/INJURY_DETAILS_ATTACHMENT_TYPE';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { MAIM_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';

export {
  EXHAUSTION_STATE_CODE,
  STUNNED_STATE_CODE,
  WOUND_STATE_CODE,
} from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { formatInjuryReceivedMessage } from '@/modules/Roleplay/Game/Utils/injuryCheckMessage';
import { rollInjury } from '@/modules/Roleplay/Game/Utils/injuryRoll';
import { checkAdvantageModifiers } from '@/modules/Roleplay/Character/Utils/stateRuntimeEffects';

export function overlayStateTotal(version: CharacterVersion | null | undefined, rules: Rule[], code: string): number {
  const rule = rules.find((item) => item.code === code && item.type === 'state');
  if (!rule || !version) return 0;

  return version.states
    .filter((state) => state.stateRuleId === rule.id)
    .reduce((sum, state) => sum + (state.value ?? 0), 0);
}

export interface InjuryInputFromAttack {
  hpDamage: number;
  cuttingWound: number | null;
  woundFromHit: number | null;
  overlayExhaustion: number;
  endurance: number;
  remainingSr: number;
  damageTypeCode: string | null;
  actorKey: CombatEntityKey;
}

/** Автоувечье только если этот удар дал повреждения или рану (не сырой урон / не один колющий бонус РУ). */
export function shouldLaunchInjuryFromAttack(
  source: Pick<InjuryInputFromAttack, 'hpDamage' | 'cuttingWound' | 'woundFromHit'>,
): boolean {
  return source.hpDamage > 0 || (source.cuttingWound ?? 0) > 0 || (source.woundFromHit ?? 0) > 0;
}

/** Повреждения и рана — этого удара; истощение — с оверлея после записи (накопленное). */
export function injuryInputFromAttack(source: InjuryInputFromAttack): InjuryRollInput {
  return {
    leftoverDamage: Math.max(0, source.hpDamage),
    woundStrength: Math.max(0, source.woundFromHit ?? 0) + Math.max(0, source.cuttingWound ?? 0),
    endurance: Math.max(1, source.endurance),
    exhaustion: Math.max(0, source.overlayExhaustion),
    attackSr: Math.max(0, source.remainingSr),
    damageTypeCode: source.damageTypeCode,
    actorKey: source.actorKey,
  };
}

export interface ApplyInjuryCheckArgs {
  input: InjuryRollInput;
  rng?: DiceRng;
  rules: Rule[];
  mechanics: Mechanic[];
  gameId: number;
  targetKey: CombatEntityKey;
  targetName: string;
  chatId: number | null;
  speaker: ChatSpeaker;
  /** Авто с атаки: сложность 0 — без чата и без состояния. */
  skipIfNoRoll?: boolean;
  /** Заголовок в чат (увечье от кровопотери). */
  chatPrefix?: string;
  targetVersion?: CharacterVersion;
  sendMessage: (
    content: string,
    attachments: ChatAttachment[],
    chatId: number,
    speaker: ChatSpeaker,
  ) => Promise<boolean>;
}

export interface ApplyInjuryCheckResult {
  roll: DiceRollResult;
  overlay: GameCombatOverlay | null;
  skipped: boolean;
}

export async function applyInjuryCheck(args: ApplyInjuryCheckArgs): Promise<ApplyInjuryCheckResult> {
  const extras = checkAdvantageModifiers(args.targetVersion, args.rules);
  const input = extras.length
    ? { ...args.input, advantages: [...(args.input.advantages ?? []), ...extras] }
    : args.input;
  const roll = rollInjury(input, args.rng ?? Math.random, args.rules, args.mechanics);
  const injury = roll.injury;
  if (!injury) throw new Error('Нет исхода увечья');
  if (args.skipIfNoRoll && roll.rolls.length === 0) {
    return { roll, overlay: null, skipped: true };
  }
  let overlay: GameCombatOverlay | null = null;
  if (injury.strength > 0) {
    const maimRule = args.rules.find((rule) => rule.code === MAIM_STATE_CODE && rule.type === 'state');
    if (maimRule) {
      overlay = await getGameApi().addCombatState(args.gameId, args.targetKey, {
        stateRuleId: maimRule.id,
        value: injury.strength,
        maim: {
          permanent: injury.permanent,
          healTotal: injury.heal?.total,
          healUnit: injury.heal?.unit,
          lethal: injury.lethal,
          disfiguring: injury.disfiguring,
        },
      } as CharacterStateValue);
    }
  }
  if (args.chatId !== null) {
    if (roll.rolls.length > 0) {
      const sentRoll = await args.sendMessage(
        '',
        [{ type: ROLL_ATTACHMENT_TYPE, payload: roll }],
        args.chatId,
        args.speaker,
      );
      if (!sentRoll) throw new Error('Не удалось отправить бросок увечья');
    }
    const typeCode = args.input.damageTypeCode;
    const typeRule = typeCode ? args.rules.find((rule) => rule.code === typeCode) : undefined;
    const details =
      roll.rolls.length > 0
        ? [
            {
              type: INJURY_DETAILS_ATTACHMENT_TYPE,
              payload: { injury, damageTypeName: typeRule?.name ?? null },
            },
          ]
        : [];
    const sent = await args.sendMessage(
      [args.chatPrefix, formatInjuryReceivedMessage(args.targetName, injury, args.targetKey)]
        .filter(Boolean)
        .join('\n'),
      details,
      args.chatId,
      args.speaker,
    );
    if (!sent) throw new Error('Не удалось отправить проверку на увечье');
  }

  return { roll, overlay, skipped: false };
}
