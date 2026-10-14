import { CharacterHttpViewService } from '@/modules/Roleplay/Character/Service/CharacterHttpViewService';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';

export const characterHttpViewService = new CharacterHttpViewService(characterBuildService, characterEditorService);
