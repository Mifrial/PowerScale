import type { ProcessStrikePackage } from '@/modules/Roleplay/Game/Dto/ProcessStrikePackage';

/** Доступный шаг сессии процесса из game.getBattleSheets. */
export interface ProcessSessionStep {
  code: string;
  name: string;
  description: string;
  actionPoints: number;
  execution: 'strike' | 'action' | 'none';
  actionCode?: string | null;
  strike?: ProcessStrikePackage | null;
}
