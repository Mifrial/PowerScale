import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';

/** Отложенный каст между проверкой и выбором насыщения. */
export interface GamePhpPendingSpellCast {
  castId: string;
  actionId: number;
  actor: GamePhpParticipant;
  userId: number;
  spellCode: string;
  pathCode: string;
  check: {
    difficulty: DimensionalNumberValue;
    needsCheck: boolean;
    roll: Record<string, unknown> | null;
    castOk: boolean;
  };
  trainingDelta: number;
  deliverySr?: number | null;
  cost: number;
  casterVersion: number;
  target: (GamePhpParticipant & { version: number }) | null;
  touch: boolean;
  strikeId: number | null;
  saturation: {
    sourceCode: string;
    minRating: number;
    ratingPerStep: number;
    powerPerStep: number;
    rating: number;
    maxSteps: number;
  } | null;
  createdAt: number;
}
