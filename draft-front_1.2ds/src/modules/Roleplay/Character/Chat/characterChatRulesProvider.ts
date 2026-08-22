import { getRuleApi } from '@/modules/Roleplay/Rule/init';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import type { IChatRulesProvider } from '@/modules/Messages/Chat/Interface/IChatRulesProvider';
import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { ICharacterApi } from '@/modules/Roleplay/Character/Interface/ICharacterApi';

// Кэш обратного маппинга чат → персонаж (карточка списка не несёт discussionChatId).
const characterByChatCache = new Map<number, CharacterDetail | null>();

async function characterApi(): Promise<ICharacterApi> {
  // Динамический импорт: провайдер регистрируется в Character/init — статический импорт
  // фасада создал бы цикл (Character/init → provider → Character/init).
  const { getCharacterApi } = await import('@/modules/Roleplay/Character/init');

  return getCharacterApi();
}

async function findCharacterByChat(chatId: number): Promise<CharacterDetail | null> {
  if (characterByChatCache.has(chatId)) return characterByChatCache.get(chatId) ?? null;
  const api = await characterApi();
  const characters = await api.getCharacters();
  for (const character of characters) {
    const detail = await api.getCharacter(character.id);
    if (detail.discussionChatId === chatId) {
      characterByChatCache.set(chatId, detail);

      return detail;
    }
  }
  characterByChatCache.set(chatId, null);

  return null;
}

/**
 * Правила обсуждения персонажа: ревизия персонажа (обратный маппинг по discussionChatId).
 * Нужно, чтобы ссылки [[rule:...]] и броски в мессенджере резолвились из ревизии персонажа.
 */
export const characterChatRulesProvider: IChatRulesProvider = {
  types: ['character_discussion'],
  resolve: async (_type, chatId) => {
    const character = await findCharacterByChat(chatId);
    if (!character) return null;
    const revision = await useSpaceRevisionStore().fetchRevision(
      character.character.spaceId,
      character.version.rulesRevision,
    );
    const mechanics = await getRuleApi().getMechanics();

    return {
      rules: revision.rules,
      mechanics,
      spaceId: character.character.spaceId,
      rulesRevision: character.version.rulesRevision,
    };
  },
};
