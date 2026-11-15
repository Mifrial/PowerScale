import { useSpaceRevision } from '@/modules/Roleplay/RuleSpace/init';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { gameNoteContextService } from '@/modules/Roleplay/Game/Service/Instance/gameNoteContextService';
import { NpcNoteHostService } from '@/modules/Roleplay/Game/Service/NpcNoteHostService';

export const npcNoteHostService = new NpcNoteHostService(
  (npcId) => getGameApi().findNpc(npcId),
  (gameId) => getGameApi().getGame(gameId),
  (spaceId, rulesRevision) => useSpaceRevision().fetchRevision(spaceId, rulesRevision),
  gameNoteContextService,
);
