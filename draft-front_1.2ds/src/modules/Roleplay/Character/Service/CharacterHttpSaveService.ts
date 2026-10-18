import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterCreateRequest } from '@/modules/Roleplay/Character/Dto/CharacterCreateRequest';
import type { CharacterValidateRequest } from '@/modules/Roleplay/Character/Dto/CharacterValidateRequest';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';

/**
 * Собирает JSON character.create / character.validate под PHP CreateCharacterInput.
 */
export class CharacterHttpSaveService {
  readCreatedId(payload: unknown): number {
    if (payload !== null && typeof payload === 'object') {
      if (
        'character' in payload &&
        payload.character !== null &&
        typeof payload.character === 'object' &&
        'id' in payload.character &&
        typeof payload.character.id === 'number'
      ) {
        return payload.character.id;
      }
      if ('id' in payload && typeof payload.id === 'number') {
        return payload.id;
      }
    }

    throw new Error('Character create failed');
  }

  createBody(request: CharacterCreateRequest): Record<string, unknown> {
    return this.choicesBody(request.choices, request.creationConfig, true);
  }

  validateBody(request: CharacterValidateRequest): Record<string, unknown> {
    if (request.mode === 'create') {
      return this.choicesBody(request.choices, request.creationConfig, true);
    }
    if (request.choices && request.creationConfig) {
      return {
        id: request.characterId,
        ...this.choicesBody(request.choices, request.creationConfig, false),
      };
    }

    return request as unknown as Record<string, unknown>;
  }

  updateBody(
    id: number,
    choices: CharacterBuild,
    config: CharacterCreationConfig,
    expectedVersion: number,
  ): Record<string, unknown> {
    return {
      id,
      ...this.choicesBody(choices, config, false),
      expectedVersion,
    };
  }

  /**
   * Документ choices PHP (персонаж и НПС): без spaceId/revision, они снаружи.
   */
  documentOf(choices: CharacterBuild, config: CharacterCreationConfig): Record<string, unknown> {
    const body = this.choicesBody(choices, config, false);

    return {
      name: body.name,
      shortDescription: body.shortDescription,
      fullDescription: body.fullDescription,
      ageYears: body.ageYears,
      limits: body.limits,
      raceCode: body.raceCode,
      characteristicPurchases: body.characteristicPurchases,
      abilities: body.abilities,
      inventory: body.inventory,
      customRules: body.customRules,
      money: choices.money,
      active: true,
    };
  }

  documentFromVersion(version: CharacterVersion, config: CharacterCreationConfig): Record<string, unknown> {
    return this.documentOf(
      {
        name: version.name,
        shortDescription: version.shortDescription,
        fullDescription: version.fullDescription,
        spaceId: 0,
        spaceCode: version.spaceCode,
        rulesRevision: version.rulesRevision,
        raceRuleCode: version.raceRuleCode,
        characteristicPurchases: version.characteristicPurchases ?? [],
        abilities: version.abilities,
        resources: version.resources,
        inventory: version.inventory,
        states: version.states,
        money: version.money,
        ageYears: version.ageYears,
        olTotal: version.points.olTotal,
        ethnicityCode: version.ethnicityCode,
        ethnicityText: version.ethnicityText,
        nativeLanguageCode: version.nativeLanguageCode,
        nativeLanguageText: version.nativeLanguageText,
      },
      config,
    );
  }

  migrateRemapBody(id: number, revision: number, expectedVersion: number): Record<string, unknown> {
    return { id, revision, expectedVersion };
  }

  migrateResumeBody(
    id: number,
    revision: number,
    expectedVersion: number,
    choices: CharacterBuild,
    config: CharacterCreationConfig,
  ): Record<string, unknown> {
    const limits: Record<string, unknown> = { os: config.osTotal, or: config.orTotal };

    return {
      id,
      revision,
      expectedVersion,
      name: choices.name,
      shortDescription: choices.shortDescription ?? '',
      fullDescription: choices.fullDescription ?? '',
      ageYears: choices.ageYears,
      limits,
      raceCode: choices.raceRuleCode ?? '',
      characteristicPurchases: choices.characteristicPurchases.map((row) => ({
        characteristicCode: row.characteristicCode,
        cost: row.cost,
      })),
      abilities: this.abilityRows(choices.abilities),
      inventory: choices.inventory.map((item) => this.inventoryRow(item)),
      customRules: [],
      money: choices.money,
    };
  }

  private choicesBody(
    choices: CharacterBuild,
    config: CharacterCreationConfig,
    allowMoneyLimit: boolean,
  ): Record<string, unknown> {
    const limits: Record<string, unknown> = { os: config.osTotal, or: config.orTotal };
    if (allowMoneyLimit) limits.money = config.moneyBudget;

    return {
      spaceId: choices.spaceId,
      revision: choices.rulesRevision,
      name: choices.name,
      shortDescription: choices.shortDescription ?? '',
      fullDescription: choices.fullDescription ?? '',
      ageYears: choices.ageYears,
      limits,
      raceCode: choices.raceRuleCode ?? '',
      characteristicPurchases: choices.characteristicPurchases.map((row) => ({
        characteristicCode: row.characteristicCode,
        cost: row.cost,
      })),
      abilities: this.abilityRows(choices.abilities),
      inventory: choices.inventory.map((item) => this.inventoryRow(item)),
      customRules: [],
    };
  }

  private abilityRows(abilities: CharacterAbility[]): Record<string, unknown>[] {
    const hasDomain = new Set(
      abilities.filter((ability) => this.abilityDomain(ability) !== '').map((ability) => ability.ruleCode),
    );
    const unique = new Map<string, CharacterAbility>();
    for (const ability of abilities) {
      if (this.abilityDomain(ability) === '' && hasDomain.has(ability.ruleCode)) continue;
      const key = `${ability.ruleCode}:${ability.domainCode ?? ''}:${ability.domain ?? ''}`;
      const previous = unique.get(key);
      if (!previous || ability.level > previous.level) unique.set(key, ability);
    }

    return [...unique.values()].map((ability) => this.abilityRow(ability));
  }

  private abilityDomain(ability: CharacterAbility): string {
    return (ability.domainCode ?? ability.domain ?? '').trim();
  }

  private abilityRow(ability: CharacterAbility): Record<string, unknown> {
    const row: Record<string, unknown> = { ruleCode: ability.ruleCode, level: ability.level };
    if (ability.zone !== undefined) row.zone = ability.zone;
    if (ability.parameters !== undefined) row.parameters = ability.parameters;
    if (ability.domain !== undefined) row.domain = ability.domain;
    if (ability.domainCode !== undefined) row.domainCode = ability.domainCode;

    return row;
  }

  private inventoryRow(item: InventoryItem): Record<string, unknown> {
    const row: Record<string, unknown> = {
      id: item.id,
      quantity: item.quantity,
      equipped: item.equipped,
    };
    if (item.ruleCode !== null) row.ruleCode = item.ruleCode;
    else {
      row.custom = { name: item.name ?? '', description: item.description ?? '' };
    }
    if (item.modifierRuleCodes !== undefined) row.modifiers = item.modifierRuleCodes;
    if (item.durabilityLeft !== undefined) row.durabilityLeft = item.durabilityLeft;
    if (item.note !== undefined) row.note = item.note;

    return row;
  }
}
