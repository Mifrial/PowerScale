import type { Character } from '@/modules/Roleplay/Character/Dto/Character';
import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { CharacterCreateRequest } from '@/modules/Roleplay/Character/Dto/CharacterCreateRequest';
import type { CharacterUpdateRequest } from '@/modules/Roleplay/Character/Dto/CharacterUpdateRequest';
import type { CharacterValidateRequest } from '@/modules/Roleplay/Character/Dto/CharacterValidateRequest';
import type { CharacterValidationResult } from '@/modules/Roleplay/Character/Dto/CharacterValidationResult';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { CharacterCustomRuleCreateRequest } from '@/modules/Roleplay/Character/Dto/CharacterCustomRuleCreateRequest';
import type { CharacterCustomRuleUpdateRequest } from '@/modules/Roleplay/Character/Dto/CharacterCustomRuleUpdateRequest';
import type { MigrationResult } from '@/modules/Roleplay/Character/Dto/MigrationResult';
import type { CharacterMigrationApplyRequest } from '@/modules/Roleplay/Character/Dto/CharacterMigrationApplyRequest';

export interface ICharacterApi {
  getCharacters(signal?: AbortSignal): Promise<Character[]>;
  getCharacter(id: number, signal?: AbortSignal): Promise<CharacterDetail>;
  createCharacter(data: CharacterCreateRequest, signal?: AbortSignal): Promise<CharacterDetail>;
  updateCharacter(id: number, data: CharacterUpdateRequest, signal?: AbortSignal): Promise<CharacterDetail>;
  validateCharacter(data: CharacterValidateRequest, signal?: AbortSignal): Promise<CharacterValidationResult>;
  updateVisibility(id: number, visibility: SheetVisibility, signal?: AbortSignal): Promise<Character>;
  addCustomRule(id: number, data: CharacterCustomRuleCreateRequest, signal?: AbortSignal): Promise<CharacterDetail>;
  updateCustomRule(
    id: number,
    entryId: number,
    data: CharacterCustomRuleUpdateRequest,
    signal?: AbortSignal,
  ): Promise<CharacterDetail>;
  migrateCharacter(
    id: number,
    target: { toSpaceId: number; toRevision: number },
    signal?: AbortSignal,
  ): Promise<MigrationResult>;
  applyMigration(id: number, request: CharacterMigrationApplyRequest, signal?: AbortSignal): Promise<CharacterDetail>;
  updateOwnerNotes(id: number, notes: string, signal?: AbortSignal): Promise<CharacterDetail>;
}
