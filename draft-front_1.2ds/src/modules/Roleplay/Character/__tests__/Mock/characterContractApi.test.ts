import { describe, expect, it } from 'vitest';
import { mockCharacterApi } from '@/modules/Roleplay/Character/Mock/mockCharacterApi';
import {
  captureCharacterRuntimeState,
  restoreCharacterRuntimeState,
  versions,
} from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { characterPatchService } from '@/modules/Roleplay/Character/Service/Instance/characterPatchService';
import { characterChangePort } from '@/modules/Roleplay/Character/Service/Instance/characterChangePort';
import { fetchRevision } from '@/modules/Roleplay/RuleSpace/Mock/mockSpaces';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';

async function createContractCharacter(
  creationConfig: CharacterCreationConfig = { osTotal: null, orTotal: null, moneyBudget: null },
) {
  const rules = (await fetchRevision(1, 5)).rules;
  const choices = characterBuildService.fromVersion(versions[1], 1, rules);

  return mockCharacterApi.createCharacter({
    commandId: crypto.randomUUID(),
    choices,
    creationConfig,
  });
}

describe('mock Character contract', () => {
  it('сохраняет контекст бюджетов при создании из choices', async () => {
    const created = await createContractCharacter({ osTotal: 12, orTotal: 25, moneyBudget: 100 });

    expect(created.version.budgets).toEqual({ osTotal: 12, moneyBudget: 100 });
    expect(created.version.points.orTotal).toBe(25);
  });

  it('in-game editor пишет actual через общий Character API, а не в overlay', async () => {
    const before = captureCharacterRuntimeState(1);
    try {
      const patch = characterPatchService.createPatch(
        before.version,
        { ...before.version, money: before.version.money + 13 },
        'contract-in-game-actual',
        before.actualVersion,
      );

      const updated = await mockCharacterApi.updateCharacter(1, { patch });

      expect(updated.version.money).toBe(before.version.money + 13);
      expect(updated.actualVersion).toBe(before.actualVersion + 1);
    } finally {
      restoreCharacterRuntimeState(1, before);
    }
  });

  it('создаёт из choices и обновляет actual через typed patch + CAS', async () => {
    const created = await createContractCharacter();
    const patch = characterPatchService.createPatch(
      created.version,
      { ...created.version, money: created.version.money + 11 },
      'contract-update-1',
      created.actualVersion,
    );

    const updated = await mockCharacterApi.updateCharacter(created.character.id, { patch });

    expect(updated.version.money).toBe(created.version.money + 11);
    expect(updated.actualVersion).toBe(created.actualVersion + 1);
  });

  it('не применяет stale patch и не меняет actual', async () => {
    const created = await createContractCharacter();
    const patch = characterPatchService.createPatch(
      created.version,
      { ...created.version, money: created.version.money + 11 },
      'contract-update-first',
      created.actualVersion,
    );
    await mockCharacterApi.updateCharacter(created.character.id, { patch });

    const stalePatch = characterPatchService.createPatch(
      created.version,
      { ...created.version, money: created.version.money + 12 },
      'contract-update-stale',
      created.actualVersion,
    );
    await expect(mockCharacterApi.updateCharacter(created.character.id, { patch: stalePatch })).rejects.toMatchObject({
      code: 'CHARACTER_ACTUAL_CONFLICT',
    });
    const current = await mockCharacterApi.getCharacter(created.character.id);
    expect(current.version.money).toBe(created.version.money + 11);
    expect(current.actualVersion).toBe(created.actualVersion + 1);
  });

  it('возвращает прежний result при повторе того же commandId', async () => {
    const created = await createContractCharacter();
    const patch = characterPatchService.createPatch(
      created.version,
      { ...created.version, money: created.version.money + 7 },
      'contract-update-idempotent',
      created.actualVersion,
    );

    const first = await mockCharacterApi.updateCharacter(created.character.id, { patch });
    const second = await mockCharacterApi.updateCharacter(created.character.id, { patch });

    expect(second).toEqual(first);
  });

  it('публикует CharacterChanged только после первой успешной mutation', async () => {
    const changes: number[] = [];
    const stop = characterChangePort.subscribe((change) => {
      changes.push(change.actualVersion);
    });
    try {
      const created = await createContractCharacter();
      const patch = characterPatchService.createPatch(
        created.version,
        { ...created.version, money: created.version.money + 8 },
        'contract-change-fact',
        created.actualVersion,
      );

      await mockCharacterApi.updateCharacter(created.character.id, { patch });
      await mockCharacterApi.updateCharacter(created.character.id, { patch });

      expect(changes).toEqual([created.actualVersion + 1]);
    } finally {
      stop();
    }
  });

  it('защищает custom-rule command теми же CAS и idempotency правилами', async () => {
    const created = await createContractCharacter();
    const command = {
      commandId: 'custom-rule-command-1',
      expectedActualVersion: created.actualVersion,
      kind: 'ability' as const,
      name: 'Странная интуиция',
      description: 'Одноразовая тестовая способность',
    };

    const first = await mockCharacterApi.addCustomRule(created.character.id, command);
    const replay = await mockCharacterApi.addCustomRule(created.character.id, command);

    expect(replay).toEqual(first);
    expect(first.actualVersion).toBe(created.actualVersion + 1);

    await expect(
      mockCharacterApi.addCustomRule(created.character.id, {
        ...command,
        commandId: 'custom-rule-command-stale',
        name: 'Другая способность',
      }),
    ).rejects.toMatchObject({ code: 'CHARACTER_ACTUAL_CONFLICT' });
  });

  it('сериализует параллельный replay одного commandId', async () => {
    const created = await createContractCharacter();
    const patch = characterPatchService.createPatch(
      created.version,
      { ...created.version, money: created.version.money + 9 },
      'contract-update-concurrent-replay',
      created.actualVersion,
    );

    const [first, replay] = await Promise.all([
      mockCharacterApi.updateCharacter(created.character.id, { patch }),
      mockCharacterApi.updateCharacter(created.character.id, { patch }),
    ]);

    expect(replay).toEqual(first);
    expect(first.actualVersion).toBe(created.actualVersion + 1);
  });

  it('отклоняет один из параллельных команд с одной устаревшей версией', async () => {
    const created = await createContractCharacter();
    const firstPatch = characterPatchService.createPatch(
      created.version,
      { ...created.version, money: created.version.money + 3 },
      'contract-update-concurrent-first',
      created.actualVersion,
    );
    const secondPatch = characterPatchService.createPatch(
      created.version,
      { ...created.version, money: created.version.money + 4 },
      'contract-update-concurrent-second',
      created.actualVersion,
    );

    const results = await Promise.allSettled([
      mockCharacterApi.updateCharacter(created.character.id, { patch: firstPatch }),
      mockCharacterApi.updateCharacter(created.character.id, { patch: secondPatch }),
    ]);

    expect(results.filter((result) => result.status === 'fulfilled')).toHaveLength(1);
    expect(results.filter((result) => result.status === 'rejected')).toHaveLength(1);
    expect((results.find((result) => result.status === 'rejected') as PromiseRejectedResult).reason).toMatchObject({
      code: 'CHARACTER_ACTUAL_CONFLICT',
    });
  });

  it('применяет migration с CAS и возвращает прежний result при replay', async () => {
    const created = await createContractCharacter();
    const request = {
      commandId: 'contract-migration-1',
      expectedActualVersion: created.actualVersion,
      version: { ...created.version, name: `${created.version.name} migrated` },
    };

    const first = await mockCharacterApi.applyMigration(created.character.id, request);
    const replay = await mockCharacterApi.applyMigration(created.character.id, request);

    expect(replay).toEqual(first);
    expect(first.actualVersion).toBe(created.actualVersion + 1);
  });
});
