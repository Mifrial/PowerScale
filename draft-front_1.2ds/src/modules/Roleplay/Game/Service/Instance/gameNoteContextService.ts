import { getGameApi } from '@/modules/Roleplay/Game/init';
import { gameChatRulesContextService } from '@/modules/Roleplay/Game/Service/Instance/gameChatRulesContextService';
import { GameNoteContextService } from '@/modules/Roleplay/Game/Service/GameNoteContextService';

export const gameNoteContextService = new GameNoteContextService(
  (gameId) => getGameApi().getCharacterList({ gameId }),
  (query) => getGameApi().getNpcSummaries(query),
  gameChatRulesContextService,
);
