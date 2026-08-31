import type { Character } from '@/modules/Roleplay/Character/Dto/Character';
import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CreateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/CreateCharacterData';
import type { UpdateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/UpdateCharacterData';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { AddCustomRuleData } from '@/modules/Roleplay/Character/Dto/AddCustomRuleData';
import type { UpdateCustomRuleData } from '@/modules/Roleplay/Character/Dto/UpdateCustomRuleData';
import type { MigrationResult } from '@/modules/Roleplay/Character/Dto/MigrationResult';

export interface ICharacterApi {
  getCharacters(signal?: AbortSignal): Promise<Character[]>;
  getCharacter(id: number, signal?: AbortSignal): Promise<CharacterDetail>;
  createCharacter(data: CreateCharacterData, signal?: AbortSignal): Promise<CharacterDetail>;
  updateCharacter(id: number, data: UpdateCharacterData, signal?: AbortSignal): Promise<CharacterDetail>;
  updateVisibility(id: number, visibility: SheetVisibility, signal?: AbortSignal): Promise<Character>;
  addCustomRule(id: number, data: AddCustomRuleData, signal?: AbortSignal): Promise<CharacterDetail>;
  updateCustomRule(
    id: number,
    entryId: number,
    data: UpdateCustomRuleData,
    signal?: AbortSignal,
  ): Promise<CharacterDetail>;
  migrateCharacter(
    id: number,
    target: { toSpaceId: number; toRevision: number },
    signal?: AbortSignal,
  ): Promise<MigrationResult>;
  applyMigration(id: number, version: CharacterVersion, signal?: AbortSignal): Promise<CharacterDetail>;
  updateOwnerNotes(id: number, notes: string, signal?: AbortSignal): Promise<CharacterDetail>;
}
