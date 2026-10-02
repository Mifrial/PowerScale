import type { ICharacterApi } from '@/modules/Roleplay/Character/Interface/ICharacterApi';
import * as mock from '@/modules/Roleplay/Character/Mock/mockCharacters';
import type { CharacterCustomRuleCreateRequest } from '@/modules/Roleplay/Character/Dto/CharacterCustomRuleCreateRequest';
import type { CharacterCustomRuleUpdateRequest } from '@/modules/Roleplay/Character/Dto/CharacterCustomRuleUpdateRequest';
import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';
import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { CharacterMigrationApplyRequest } from '@/modules/Roleplay/Character/Dto/CharacterMigrationApplyRequest';
import { CharacterApiError } from '@/modules/Roleplay/Character/Service/CharacterApiError';
import { characterDiffService } from '@/modules/Roleplay/Character/Service/Instance/characterDiffService';
import { characterChangePort } from '@/modules/Roleplay/Character/Service/Instance/characterChangePort';
import { getCurrentUserId } from '@/modules/Core/Auth/Mock/mockAuth';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';

const completedCharacterCommands = new Map<string, { fingerprint: string; detail: CharacterDetail }>();
const completedCustomRuleCommands = new Map<string, { fingerprint: string; detail: CharacterDetail }>();
const completedMigrationCommands = new Map<string, { fingerprint: string; detail: CharacterDetail }>();
const mutationQueues = new Map<number, Promise<unknown>>();

/**
 * Mock contract adapter Character API. All Character-owned sheet mutations use actual with typed
 * CAS/idempotency envelopes; legacy overlay adapters remain outside this API boundary.
 */
export const mockCharacterApi: ICharacterApi = {
  getCharacters: mock.fetchCharacters,
  getCharacter: mock.fetchCharacter,
  createCharacter: mock.createCharacterFromChoices,
  updateCharacter: (id, data) => updateCharacter(id, data.patch),
  validateCharacter: mock.validateCharacterRequest,
  updateVisibility: mock.updateCharacterVisibility,
  addCustomRule: (id, data, signal) => addCustomRule(id, data, signal),
  updateCustomRule: (id, entryId, data, signal) => updateCustomRule(id, entryId, data, signal),
  migrateCharacter: mock.migrateCharacter,
  applyMigration: (characterId, request, signal) => applyMigration(characterId, request, signal),
  updateOwnerNotes: mock.updateOwnerNotes,
};

async function updateCharacter(id: number, patch: CharacterPatch): Promise<CharacterDetail> {
  return runSerializedMutation(id, async () => {
    const fingerprint = JSON.stringify({ id, patch });
    const previous = completedCharacterCommands.get(patch.commandId);
    if (previous) {
      if (previous.fingerprint !== fingerprint) {
        throw new CharacterApiError('CHARACTER_COMMAND_CONFLICT', 'CommandId уже использован с другими параметрами');
      }

      return cloneData(previous.detail);
    }

    const before = mock.getStoredCharacterVersion(id);
    const detail = await mock.updateCharacterFromPatch(id, patch);
    completedCharacterCommands.set(patch.commandId, { fingerprint, detail: cloneData(detail) });
    publishCharacterChanged(id, before, detail, 'character_edit');

    return detail;
  });
}

async function runSerializedMutation<T>(characterId: number, mutation: () => Promise<T>): Promise<T> {
  const previous = mutationQueues.get(characterId) ?? Promise.resolve();
  const current = previous.catch(() => undefined).then(mutation);
  mutationQueues.set(characterId, current);

  try {
    return await current;
  } finally {
    if (mutationQueues.get(characterId) === current) mutationQueues.delete(characterId);
  }
}

function assertExpectedVersion(characterId: number, expectedActualVersion: number, commandId: string): void {
  const actualVersion = mock.getCharacterActualVersion(characterId);
  if (actualVersion !== expectedActualVersion) {
    throw new CharacterApiError('CHARACTER_ACTUAL_CONFLICT', 'Актуальное состояние персонажа уже изменилось', {
      kind: 'conflict',
      expectedActualVersion,
      actualVersion,
      commandId,
    });
  }
}

async function addCustomRule(id: number, data: CharacterCustomRuleCreateRequest, signal?: AbortSignal) {
  return runSerializedMutation(id, async () => {
    const fingerprint = JSON.stringify({ id, data });
    const previous = completedCustomRuleCommands.get(data.commandId);
    if (previous) {
      if (previous.fingerprint !== fingerprint) {
        throw new CharacterApiError('CHARACTER_COMMAND_CONFLICT', 'CommandId уже использован с другими параметрами');
      }

      return cloneData(previous.detail);
    }

    assertExpectedVersion(id, data.expectedActualVersion, data.commandId);
    const payload = {
      kind: data.kind,
      name: data.name,
      description: data.description,
    };
    const before = mock.getStoredCharacterVersion(id);
    const detail = await mock.addCustomRule(id, payload, signal, data.expectedActualVersion);
    completedCustomRuleCommands.set(data.commandId, { fingerprint, detail: cloneData(detail) });
    publishCharacterChanged(id, before, detail, 'character_edit');

    return detail;
  });
}

async function updateCustomRule(
  id: number,
  entryId: number,
  data: CharacterCustomRuleUpdateRequest,
  signal?: AbortSignal,
) {
  return runSerializedMutation(id, async () => {
    const fingerprint = JSON.stringify({ id, entryId, data });
    const previous = completedCustomRuleCommands.get(data.commandId);
    if (previous) {
      if (previous.fingerprint !== fingerprint) {
        throw new CharacterApiError('CHARACTER_COMMAND_CONFLICT', 'CommandId уже использован с другими параметрами');
      }

      return cloneData(previous.detail);
    }

    assertExpectedVersion(id, data.expectedActualVersion, data.commandId);
    const payload = {
      name: data.name,
      description: data.description,
      status: data.status,
      replacedWithRuleCode: data.replacedWithRuleCode,
    };
    const before = mock.getStoredCharacterVersion(id);
    const detail = await mock.updateCustomRule(id, entryId, payload, signal, data.expectedActualVersion);
    completedCustomRuleCommands.set(data.commandId, { fingerprint, detail: cloneData(detail) });
    publishCharacterChanged(id, before, detail, 'character_edit');

    return detail;
  });
}

async function applyMigration(
  characterId: number,
  request: CharacterMigrationApplyRequest,
  signal?: AbortSignal,
): Promise<CharacterDetail> {
  return runSerializedMutation(characterId, async () => {
    const fingerprint = JSON.stringify({ characterId, request });
    const previous = completedMigrationCommands.get(request.commandId);
    if (previous) {
      if (previous.fingerprint !== fingerprint) {
        throw new CharacterApiError('CHARACTER_COMMAND_CONFLICT', 'CommandId уже использован с другими параметрами');
      }

      return cloneData(previous.detail);
    }

    assertExpectedVersion(characterId, request.expectedActualVersion, request.commandId);
    const before = mock.getStoredCharacterVersion(characterId);
    const detail = await mock.applyMigration(characterId, request.version, signal, request.expectedActualVersion);
    completedMigrationCommands.set(request.commandId, { fingerprint, detail: cloneData(detail) });
    publishCharacterChanged(characterId, before, detail, 'migration');

    return detail;
  });
}

function publishCharacterChanged(
  characterId: number,
  before: ReturnType<typeof mock.getStoredCharacterVersion>,
  detail: CharacterDetail,
  mutationKind: 'character_edit' | 'migration',
): void {
  const changedSections = [
    ...new Set(characterDiffService.getCharacterDiff(before, detail.version).changes.map((change) => change.section)),
  ];
  characterChangePort.publish({
    characterId,
    actualVersion: detail.actualVersion,
    changedSections,
    actorId: getCurrentUserId(),
    mutationKind,
  });
}
