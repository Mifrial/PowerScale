import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { ICharacterApi } from '@/modules/Roleplay/Character/Interface/ICharacterApi';
import type { Character } from '@/modules/Roleplay/Character/Dto/Character';
import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CreateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/CreateCharacterData';
import type { UpdateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/UpdateCharacterData';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { AddCustomRuleData } from '@/modules/Roleplay/Character/Dto/AddCustomRuleData';
import type { UpdateCustomRuleData } from '@/modules/Roleplay/Character/Dto/UpdateCustomRuleData';
import type { MigrationResult } from '@/modules/Roleplay/Character/Service/CharacterMigrationService';

export class CharacterApi implements ICharacterApi {
  constructor(private readonly engine: Engine) {}

  async getCharacters(signal?: AbortSignal): Promise<Character[]> {
    const res = await this.engine.runAction<Character[]>('character.getList', undefined, signal);

    return res.data ?? [];
  }

  async getCharacter(id: number, signal?: AbortSignal): Promise<CharacterDetail> {
    const res = await this.engine.runAction<CharacterDetail>('character.get', { id }, signal);
    if (!res.data) throw new Error('Character not found');

    return res.data;
  }

  async createCharacter(data: CreateCharacterData, signal?: AbortSignal): Promise<CharacterDetail> {
    const res = await this.engine.runAction<CharacterDetail>('character.create', data, signal);
    if (!res.data) throw new Error('Character create failed');

    return res.data;
  }

  async updateCharacter(id: number, data: UpdateCharacterData, signal?: AbortSignal): Promise<CharacterDetail> {
    const res = await this.engine.runAction<CharacterDetail>('character.update', { id, ...data }, signal);
    if (!res.data) throw new Error('Character update failed');

    return res.data;
  }

  async updateVisibility(id: number, visibility: SheetVisibility, signal?: AbortSignal): Promise<Character> {
    const res = await this.engine.runAction<Character>('character.updateVisibility', { id, visibility }, signal);
    if (!res.data) throw new Error('Character visibility update failed');

    return res.data;
  }

  async addCustomRule(id: number, data: AddCustomRuleData, signal?: AbortSignal): Promise<CharacterDetail> {
    const res = await this.engine.runAction<CharacterDetail>('character.addCustomRule', { id, ...data }, signal);
    if (!res.data) throw new Error('Character add custom rule failed');

    return res.data;
  }

  async updateCustomRule(
    id: number,
    entryId: number,
    data: UpdateCustomRuleData,
    signal?: AbortSignal,
  ): Promise<CharacterDetail> {
    const res = await this.engine.runAction<CharacterDetail>(
      'character.updateCustomRule',
      { id, entryId, ...data },
      signal,
    );
    if (!res.data) throw new Error('Character update custom rule failed');

    return res.data;
  }

  async migrateCharacter(
    id: number,
    target: { toSpaceId: number; toRevision: number },
    signal?: AbortSignal,
  ): Promise<MigrationResult> {
    const res = await this.engine.runAction<MigrationResult>('character.migrate', { id, ...target }, signal);
    if (!res.data) throw new Error('Character migrate failed');

    return res.data;
  }

  async applyMigration(id: number, version: CharacterVersion, signal?: AbortSignal): Promise<CharacterDetail> {
    const res = await this.engine.runAction<CharacterDetail>('character.applyMigration', { id, version }, signal);
    if (!res.data) throw new Error('Character apply migration failed');

    return res.data;
  }

  async updateOwnerNotes(id: number, notes: string, signal?: AbortSignal): Promise<CharacterDetail> {
    const res = await this.engine.runAction<CharacterDetail>('character.updateOwnerNotes', { id, notes }, signal);
    if (!res.data) throw new Error('Character notes update failed');

    return res.data;
  }
}
