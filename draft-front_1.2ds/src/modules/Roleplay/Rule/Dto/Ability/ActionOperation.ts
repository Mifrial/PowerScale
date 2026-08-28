import type { MovementOperation } from '@/modules/Roleplay/Rule/Dto/Ability/MovementOperation';

export type ActionOperation =
  | MovementOperation
  | {
      type: 'turn';
      maxDegrees: number;
    }
  | {
      type: 'posture';
      posture: string;
    };
