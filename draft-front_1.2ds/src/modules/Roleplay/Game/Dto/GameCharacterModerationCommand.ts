import type { GameCharacterModerationAction } from '@/modules/Roleplay/Game/Enum/GameCharacterModerationAction';

/** Идемпотентная команда GM для перехода membership в moderation lifecycle. */
export interface GameCharacterModerationCommand {
  commandId: string;
  gameId: number;
  characterId: number;
  action: GameCharacterModerationAction;
  expectedActualVersion: number;
  expectedMembershipRevision: number;
}
