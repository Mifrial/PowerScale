import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';

/** Вход создания: editor choices и контекст бюджетов, без client-derived CharacterVersion. */
export interface CharacterCreateRequest {
  commandId: string;
  choices: CharacterBuild;
  creationConfig: CharacterCreationConfig;
}
