import { getRuleApi } from '@/modules/Roleplay/Rule/init';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import type { IChatRulesProvider } from '@/modules/Messages/Chat/Interface/IChatRulesProvider';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';

// Кэш обратного маппинга чат → игра (карточка списка не несёт chatId; скан с кэшем на сессию).
const gameByChatCache = new Map<number, GameDetail | null>();

async function gameApi(): Promise<IGameApi> {
  // Динамический импорт: провайдер регистрируется в Game/init — статический импорт
  // фасада создал бы цикл (Game/init → provider → Game/init).
  const { getGameApi } = await import('@/modules/Roleplay/Game/init');

  return getGameApi();
}

async function findGameByChat(chatId: number): Promise<GameDetail | null> {
  if (gameByChatCache.has(chatId)) return gameByChatCache.get(chatId) ?? null;
  const api = await gameApi();
  const games = await api.getGames();
  for (const game of games) {
    const detail = await api.getGame(game.id);
    if (detail.gameChatId === chatId || detail.discussionChatId === chatId) {
      gameByChatCache.set(chatId, detail);

      return detail;
    }
  }
  gameByChatCache.set(chatId, null);

  return null;
}

/**
 * Правила чата игры/обсуждения игры: ревизия игры, к которой относится чат (обратный
 * маппинг по gameChatId/discussionChatId). Нужно, чтобы ссылки [[rule:...]] и броски
 * в мессенджере резолвились из правильной ревизии, а не «актуальных правил».
 */
export const gameChatRulesProvider: IChatRulesProvider = {
  types: ['game', 'game_discussion'],
  resolve: async (_type, chatId) => {
    const game = await findGameByChat(chatId);
    if (!game) return null;
    const revision = await useSpaceRevisionStore().fetchRevision(game.game.spaceId, game.game.rulesRevision);
    const mechanics = await getRuleApi().getMechanics();

    return {
      rules: revision.rules,
      mechanics,
      spaceId: game.game.spaceId,
      rulesRevision: game.game.rulesRevision,
    };
  },
};
