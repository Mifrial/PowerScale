import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { HitCheckRoll } from '@/modules/Roleplay/Game/Dto/HitCheckRoll';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';

/** Исход разрешённого толчка: промах уклонения или контест. */
export type PushResolution =
  | { kind: 'dodge_miss'; rolled: HitCheckRoll }
  | {
      kind: 'contest';
      meleeRolls: DiceRollResult[] | null;
      rolled: HitCheckRoll;
      attack: AttackOverview;
      applySr: number;
      skipDamageApply: boolean;
      knockbackIpari: DimensionalNumberValue | null;
      unstableAmount: number | null;
      unstableSkippedBecauseLying: boolean;
    };
