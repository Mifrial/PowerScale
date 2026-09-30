import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';
import type { CharacterPatchOperation } from '@/modules/Roleplay/Character/Dto/CharacterPatchOperation';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

/**
 * Применяет и строит ограниченный typed patch для CharacterVersion без произвольного JSON merge.
 */
export class CharacterPatchService {
  applyPatch(version: CharacterVersion, operations: CharacterPatchOperation[]): CharacterVersion {
    let next = structuredClone(version);

    for (const operation of operations) {
      next = this.applyOperation(next, operation);
    }

    return next;
  }

  createPatch(
    before: CharacterVersion,
    after: CharacterVersion,
    commandId: string,
    expectedActualVersion: number,
  ): CharacterPatch {
    const operations: CharacterPatchOperation[] = [];

    this.appendScalarOperations(operations, before, after);
    this.appendSectionOperations(operations, before, after);

    return { commandId, expectedActualVersion, operations };
  }

  private applyOperation(version: CharacterVersion, operation: CharacterPatchOperation): CharacterVersion {
    switch (operation.kind) {
      case 'setField':
        return this.applyScalarOperation(version, operation);
      case 'replaceSection':
        return this.applySectionOperation(version, operation);
      case 'setResourceCurrent': {
        if (!version.resources.some((resource) => resource.ruleCode === operation.ruleCode)) {
          throw new Error(`Resource ${operation.ruleCode} not found`);
        }

        return {
          ...version,
          resources: version.resources.map((resource) =>
            resource.ruleCode === operation.ruleCode
              ? { ...resource, current: structuredClone(operation.current) }
              : resource,
          ),
        };
      }
      case 'setInventoryQuantity': {
        if (!version.inventory.some((item) => item.id === operation.itemId)) {
          throw new Error(`Inventory item ${operation.itemId} not found`);
        }

        return {
          ...version,
          inventory: version.inventory.map((item) =>
            item.id === operation.itemId ? { ...item, quantity: operation.quantity } : item,
          ),
        };
      }
      case 'setInventoryEquipped': {
        if (!version.inventory.some((item) => item.id === operation.itemId)) {
          throw new Error(`Inventory item ${operation.itemId} not found`);
        }

        return {
          ...version,
          inventory: version.inventory.map((item) =>
            item.id === operation.itemId ? { ...item, equipped: operation.equipped } : item,
          ),
        };
      }
    }
  }

  private applyScalarOperation(
    version: CharacterVersion,
    operation: Extract<CharacterPatchOperation, { kind: 'setField' }>,
  ): CharacterVersion {
    switch (operation.field) {
      case 'name':
        return { ...version, name: operation.value };
      case 'shortDescription':
        return { ...version, shortDescription: operation.value };
      case 'fullDescription':
        return { ...version, fullDescription: operation.value };
      case 'raceRuleCode':
        return { ...version, raceRuleCode: operation.value };
      case 'money':
        return { ...version, money: operation.value };
      case 'ageYears':
        return { ...version, ageYears: operation.value };
      case 'ethnicityCode':
        return { ...version, ethnicityCode: operation.value };
      case 'ethnicityText':
        return { ...version, ethnicityText: operation.value };
      case 'nativeLanguageCode':
        return { ...version, nativeLanguageCode: operation.value };
      case 'nativeLanguageText':
        return { ...version, nativeLanguageText: operation.value };
    }
  }

  private applySectionOperation(
    version: CharacterVersion,
    operation: Extract<CharacterPatchOperation, { kind: 'replaceSection' }>,
  ): CharacterVersion {
    switch (operation.section) {
      case 'characteristics':
        return { ...version, characteristics: structuredClone(operation.value) };
      case 'resources':
        return { ...version, resources: structuredClone(operation.value) };
      case 'abilities':
        return { ...version, abilities: structuredClone(operation.value) };
      case 'points':
        return { ...version, points: structuredClone(operation.value) };
      case 'inventory':
        return { ...version, inventory: structuredClone(operation.value) };
      case 'states':
        return { ...version, states: structuredClone(operation.value) };
      case 'senses':
        return { ...version, senses: structuredClone(operation.value) };
      case 'customRules':
        return { ...version, customRules: structuredClone(operation.value) };
    }
  }

  private appendScalarOperations(
    operations: CharacterPatchOperation[],
    before: CharacterVersion,
    after: CharacterVersion,
  ): void {
    if (before.name !== after.name) operations.push({ kind: 'setField', field: 'name', value: after.name });
    if (before.shortDescription !== after.shortDescription) {
      operations.push({ kind: 'setField', field: 'shortDescription', value: after.shortDescription });
    }
    if (before.fullDescription !== after.fullDescription) {
      operations.push({ kind: 'setField', field: 'fullDescription', value: after.fullDescription });
    }
    if (before.raceRuleCode !== after.raceRuleCode) {
      operations.push({ kind: 'setField', field: 'raceRuleCode', value: after.raceRuleCode });
    }
    if (before.money !== after.money) operations.push({ kind: 'setField', field: 'money', value: after.money });
    if (before.ageYears !== after.ageYears) {
      operations.push({ kind: 'setField', field: 'ageYears', value: after.ageYears });
    }
    if (before.ethnicityCode !== after.ethnicityCode) {
      operations.push({ kind: 'setField', field: 'ethnicityCode', value: after.ethnicityCode ?? null });
    }
    if (before.ethnicityText !== after.ethnicityText) {
      operations.push({ kind: 'setField', field: 'ethnicityText', value: after.ethnicityText ?? null });
    }
    if (before.nativeLanguageCode !== after.nativeLanguageCode) {
      operations.push({ kind: 'setField', field: 'nativeLanguageCode', value: after.nativeLanguageCode ?? null });
    }
    if (before.nativeLanguageText !== after.nativeLanguageText) {
      operations.push({ kind: 'setField', field: 'nativeLanguageText', value: after.nativeLanguageText ?? null });
    }
  }

  private appendSectionOperations(
    operations: CharacterPatchOperation[],
    before: CharacterVersion,
    after: CharacterVersion,
  ): void {
    if (this.isDifferent(before.characteristics, after.characteristics)) {
      operations.push({
        kind: 'replaceSection',
        section: 'characteristics',
        value: structuredClone(after.characteristics),
      });
    }
    if (this.isDifferent(before.resources, after.resources)) {
      operations.push({ kind: 'replaceSection', section: 'resources', value: structuredClone(after.resources) });
    }
    if (this.isDifferent(before.abilities, after.abilities)) {
      operations.push({ kind: 'replaceSection', section: 'abilities', value: structuredClone(after.abilities) });
    }
    if (this.isDifferent(before.points, after.points)) {
      operations.push({ kind: 'replaceSection', section: 'points', value: structuredClone(after.points) });
    }
    if (this.isDifferent(before.inventory, after.inventory)) {
      operations.push({ kind: 'replaceSection', section: 'inventory', value: structuredClone(after.inventory) });
    }
    if (this.isDifferent(before.states, after.states)) {
      operations.push({ kind: 'replaceSection', section: 'states', value: structuredClone(after.states) });
    }
    if (this.isDifferent(before.senses, after.senses)) {
      operations.push({ kind: 'replaceSection', section: 'senses', value: structuredClone(after.senses) });
    }
    if (this.isDifferent(before.customRules ?? [], after.customRules ?? [])) {
      operations.push({
        kind: 'replaceSection',
        section: 'customRules',
        value: structuredClone(after.customRules ?? []),
      });
    }
  }

  private isDifferent(before: unknown, after: unknown): boolean {
    return JSON.stringify(before) !== JSON.stringify(after);
  }
}
