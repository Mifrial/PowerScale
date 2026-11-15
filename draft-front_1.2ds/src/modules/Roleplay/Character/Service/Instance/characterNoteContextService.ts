import { getCharacterApi } from '@/modules/Roleplay/Character/init';
import { characterAccessService } from '@/modules/Roleplay/Character/Service/Instance/characterAccessService';
import { characterChatRulesContextService } from '@/modules/Roleplay/Character/Service/Instance/characterChatRulesContextService';
import { CharacterNoteContextService } from '@/modules/Roleplay/Character/Service/CharacterNoteContextService';

export const characterNoteContextService = new CharacterNoteContextService(
  () => getCharacterApi().getCharacters(),
  characterChatRulesContextService,
  characterAccessService,
);
