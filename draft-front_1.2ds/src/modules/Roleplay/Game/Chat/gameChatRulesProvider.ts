import { getRuleApi } from '@/modules/Roleplay/Rule/init';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import type { IChatRulesProvider } from '@/modules/Messages/Chat/Interface/IChatRulesProvider';
import type { Game } from '@/modules/Roleplay/Game/Dto/Game';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { gameChatRulesContextService } from '@/modules/Roleplay/Game/Service/Instance/gameChatRulesContextService';

async function gameApi(): Promise<IGameApi> {
  // Динамический импорт: провайдер регистрируется в Game/init — статический импорт
  // фасада создал бы цикл (Game/init → provider → Game/init).
  const { getGameApi } = await import('@/modules/Roleplay/Game/init');

  return getGameApi();
}

async function findGameByChat(chatId: number): Promise<Game | null> {
  const api = await gameApi();
  const games = await api.getGames();

  return games.find((game) => game.gameChatId === chatId || game.discussionChatId === chatId) ?? null;
}

/**
 * Правила чата игры/обсуждения: ревизия с карточки списка (spaceId + rulesRevision).
 * Чипы [[rule:...]] и броски в мессенджере резолвятся из ревизии игры, не «актуальных правил».
 */
export const gameChatRulesProvider: IChatRulesProvider = {
  types: ['game', 'game_discussion'],
  resolve: async (_type, chatId) => {
    const game = await findGameByChat(chatId);
    if (!game) return null;
    const revision = await useSpaceRevisionStore().fetchRevision(game.spaceId, game.rulesRevision);
    const mechanics = await getRuleApi().getMechanics();

    return gameChatRulesContextService.buildChatRulesContext(
      revision.rules,
      mechanics,
      game.spaceId,
      game.rulesRevision,
    );
  },
};
