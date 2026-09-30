import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';

export type GameNpcSummary = Omit<GameNpc, 'version'> & {
  actualSpaceCode: string | null;
  actualRulesRevision: number | null;
};
