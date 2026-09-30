import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { ICharacterApi } from '@/modules/Roleplay/Character/Interface/ICharacterApi';
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
import { CharacterApiError } from '@/modules/Roleplay/Character/Service/CharacterApiError';
import type { ActionResponse } from '@/modules/Core/Engine/Dto/ActionResponse';

export class CharacterApi implements ICharacterApi {
  constructor(private readonly engine: Engine) {}

  async getCharacters(signal?: AbortSignal): Promise<Character[]> {
    const res = await this.engine.runAction<Character[]>('character.getList', undefined, signal);

    return this.requireData(res, 'Failed to fetch characters');
  }

  async getCharacter(id: number, signal?: AbortSignal): Promise<CharacterDetail> {
    const res = await this.engine.runAction<CharacterDetail>('character.get', { id }, signal);

    return this.requireData(res, 'Character not found');
  }

  async createCharacter(data: CharacterCreateRequest, signal?: AbortSignal): Promise<CharacterDetail> {
    const res = await this.engine.runAction<CharacterDetail>('character.create', data, signal);

    return this.requireData(res, 'Character create failed');
  }

  async updateCharacter(id: number, data: CharacterUpdateRequest, signal?: AbortSignal): Promise<CharacterDetail> {
    const res = await this.engine.runAction<CharacterDetail>('character.update', { id, ...data }, signal);

    return this.requireData(res, 'Character update failed');
  }

  async validateCharacter(data: CharacterValidateRequest, signal?: AbortSignal): Promise<CharacterValidationResult> {
    const res = await this.engine.runAction<CharacterValidationResult>('character.validate', data, signal);

    return this.requireData(res, 'Character validation failed');
  }

  async updateVisibility(id: number, visibility: SheetVisibility, signal?: AbortSignal): Promise<Character> {
    const res = await this.engine.runAction<Character>('character.updateVisibility', { id, visibility }, signal);

    return this.requireData(res, 'Character visibility update failed');
  }

  async addCustomRule(
    id: number,
    data: CharacterCustomRuleCreateRequest,
    signal?: AbortSignal,
  ): Promise<CharacterDetail> {
    const res = await this.engine.runAction<CharacterDetail>('character.addCustomRule', { id, ...data }, signal);

    return this.requireData(res, 'Character add custom rule failed');
  }

  async updateCustomRule(
    id: number,
    entryId: number,
    data: CharacterCustomRuleUpdateRequest,
    signal?: AbortSignal,
  ): Promise<CharacterDetail> {
    const res = await this.engine.runAction<CharacterDetail>(
      'character.updateCustomRule',
      { id, entryId, ...data },
      signal,
    );

    return this.requireData(res, 'Character update custom rule failed');
  }

  async migrateCharacter(
    id: number,
    target: { toSpaceId: number; toRevision: number },
    signal?: AbortSignal,
  ): Promise<MigrationResult> {
    const res = await this.engine.runAction<MigrationResult>('character.migrate', { id, ...target }, signal);

    return this.requireData(res, 'Character migrate failed');
  }

  async applyMigration(
    id: number,
    request: CharacterMigrationApplyRequest,
    signal?: AbortSignal,
  ): Promise<CharacterDetail> {
    const res = await this.engine.runAction<CharacterDetail>('character.applyMigration', { id, ...request }, signal);

    return this.requireData(res, 'Character apply migration failed');
  }

  async updateOwnerNotes(id: number, notes: string, signal?: AbortSignal): Promise<CharacterDetail> {
    const res = await this.engine.runAction<CharacterDetail>('character.updateOwnerNotes', { id, notes }, signal);

    return this.requireData(res, 'Character notes update failed');
  }

  private requireData<T>(response: ActionResponse<T>, fallbackMessage: string): T {
    if (!response.success || response.data === null) {
      if (response.error) throw CharacterApiError.fromActionError(response.error);

      throw new CharacterApiError('CHARACTER_EMPTY_RESPONSE', fallbackMessage);
    }

    return response.data;
  }
}
