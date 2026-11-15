import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';

/** Ответ продолжения поддержания. */
export interface GamePhpActiveSpellResult {
  spell: ActiveSpell | null;
  dropped?: boolean;
  reason?: string;
  actionCost: number;
  battleId: number;
  version: number;
  sheetVersion?: number;
}
