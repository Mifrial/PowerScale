import type { IChatRulesProvider } from '@/modules/Messages/Chat/Interface/IChatRulesProvider';
import type { Character } from '@/modules/Roleplay/Character/Dto/Character';
import type { ICharacterApi } from '@/modules/Roleplay/Character/Interface/ICharacterApi';
import { characterChatRulesContextService } from '@/modules/Roleplay/Character/Service/Instance/characterChatRulesContextService';
import { useSpaceRevision } from '@/modules/Roleplay/Space/init';

async function characterApi(): Promise<ICharacterApi> {
  // Динамический импорт: провайдер регистрируется в Character/init — статический импорт
  // фасада создал бы цикл (Character/init → provider → Character/init).
  const { getCharacterApi } = await import('@/modules/Roleplay/Character/init');

  return getCharacterApi();
}

async function findCharacterByChat(chatId: number): Promise<Character | null> {
  const api = await characterApi();
  const characters = await api.getCharacters();

  return characters.find((character) => character.discussionChatId === chatId) ?? null;
}

/**
 * Правила обсуждения персонажа: ревизия с карточки списка (spaceId + rulesRevision).
 * Чипы [[rule:...]] в мессенджере резолвятся из ревизии персонажа; броски — зона Game.
 */
export const characterChatRulesProvider: IChatRulesProvider = {
  types: ['character_discussion'],
  resolve: async (_type, chatId) => {
    const character = await findCharacterByChat(chatId);
    if (!character) return null;
    const revision = await useSpaceRevision().fetchRevision(character.spaceId, character.rulesRevision);

    return characterChatRulesContextService.build(revision.rules, character.spaceId, character.rulesRevision);
  },
};
