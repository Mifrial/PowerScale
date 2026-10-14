import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';

/** Запись character.migrate: remap без choices, resume — с choices и лимитами. */
export interface CharacterMigrateRequest {
  revision: number;
  expectedVersion: number;
  choices?: CharacterBuild;
  creationConfig?: CharacterCreationConfig;
}
