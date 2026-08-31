import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { AbilityCost } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityCost';
import type { SenseSpec } from '@/modules/Roleplay/Rule/Dto/SenseSpec';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { MigrationResult } from '@/modules/Roleplay/Character/Dto/MigrationResult';

export type MigrationProblemKind =
  'removedRule' | 'raceRemoved' | 'raceBroken' | 'lostCharacteristic' | 'unmetRequirement' | 'budgetOverrun';

/** Проблема миграции: удалённое правило, снятая раса, потерянные характеристики, требования, перерасход. */
export interface MigrationProblem {
  kind: MigrationProblemKind;
  /** Человекочитаемая подпись (имя правила из старой ревизии). */
  label: string;
  detail: string | null;
}

/** Изменение при миграции: характеристика/ресурс/бюджет — было → стало. */
export interface MigrationDiffItem {
  label: string;
  before: string;
  after: string;
  /** 'changed' — нейтрально; 'green' — выгода (освободились очки); 'red' — перерасход. */
  tone: 'changed' | 'green' | 'red';
  /** Пояснение (для бюджетов): сброшенные/пересчитанные способности. */
  explanation?: string | null;
}

/** Изменение способности при миграции (сброшена/пересчитана/добавлена) с затратами по зоне. */
export interface MigrationAbilityChange {
  label: string;
  kind: 'added' | 'removed' | 'changed';
  /** Зона затрат (os/or/ol); null — авто-способность без цены. */
  zone: string | null;
  costBefore: number | null;
  costAfter: number | null;
}

export type MigrationKind = 'ok' | 'resolved' | 'conflicts';

export interface MigrationInput {
  version: CharacterVersion;
  /** Правила старой ревизии (текущей версии персонажа). */
  oldRules: Rule[];
  oldSpaceId: number;
  /** Правила целевой ревизии. */
  newRules: Rule[];
  newSpaceId: number;
  newSpaceCode: string;
  newRevision: number;
  /** Реальные лимиты персонажа = лимит игры + гранты ГМ (для standalone — стартовые лимиты). */
  effectiveLimits: { osTotal: number | null; orTotal: number | null; moneyBudget: number | null };
  keywords?: Keyword[];
  mechanics?: Mechanic[];
}

function dimValue(value: { base: number; size: number }): string {
  return DimensionalNumber.from(value).toString();
}

const ZONE_LABEL: Record<string, string> = { os: 'ОС', or: 'ОР', ol: 'ОЛ' };

/**
 * Перевод персонажа с одной ревизии правил на другую (ТР §7, `/characters/:id/migrate`).
 * Ремап ссылок по семантическому `code` правила (глобально уникален — работает между
 * пространствами). Инвентарь не перезакупается: предметы переносятся как есть, с удалённым
 * правилом — превращаются в кастомные «предметы мастера».
 *
 * Диф честный: сравниваются обе МОДЕЛИ (старая на старых правилах и новая на новых) — значения
 * характеристик/ресурсов/бюджетов, а не сохранённое против пересчитанного. Сброшенные и
 * пересчитанные способности объясняют бюджетные сдвиги.
 *
 * Классификация: ok / resolved / conflicts (удалённые правила, невыполненные требования,
 * перерасход реальных лимитов, удалена раса).
 */
export class CharacterMigrationService {
  migrate(input: MigrationInput): MigrationResult {
    const { version, oldRules, newRules } = input;
    const codeById = new Map(oldRules.map((rule) => [rule.id, rule.code]));
    const newCodeById = new Map(newRules.map((rule) => [rule.id, rule.code]));
    const ruleByCode = new Map(newRules.map((rule) => [rule.code, rule]));
    const problems: MigrationProblem[] = [];

    const oldNameOf = (ruleId: string): string => oldRules.find((rule) => rule.id === ruleId)?.name ?? ruleId;

    const remap = (oldRuleId: string, isRace: boolean, label: string): string | null => {
      const code = codeById.get(oldRuleId);
      const rule = code === undefined ? undefined : ruleByCode.get(code);
      if (!rule) {
        if (isRace) problems.push({ kind: 'raceRemoved', label, detail: 'Расы нет в новой версии правил' });
        else problems.push({ kind: 'removedRule', label, detail: 'Правило удалено из новой версии' });

        return null;
      }

      return rule.id;
    };

    const raceRuleId =
      version.raceRuleId === null ? null : remap(version.raceRuleId, true, oldNameOf(version.raceRuleId));

    const characteristics = [];
    for (const characteristic of version.characteristics) {
      const newId = remap(characteristic.ruleId, false, oldNameOf(characteristic.ruleId));
      if (newId === null) continue;
      characteristics.push({ ...characteristic, ruleId: newId });
    }

    const resources = [];
    for (const resource of version.resources) {
      const newId = remap(resource.ruleId, false, oldNameOf(resource.ruleId));
      if (newId === null) continue;
      resources.push({ ...resource, ruleId: newId });
    }

    const remappedAbilities = [];
    for (const ability of version.abilities) {
      const newId = remap(ability.ruleId, false, oldNameOf(ability.ruleId));
      if (newId === null) continue;
      remappedAbilities.push({ ...ability, ruleId: newId });
    }

    // Инвентарь не перезакупается; предмет с удалённым правилом → кастомный «предмет мастера».
    let convertedItems = 0;
    const inventory = [];
    for (const item of version.inventory) {
      if (item.ruleId === null) {
        inventory.push(item);
        continue;
      }
      const code = codeById.get(item.ruleId);
      const rule = code === undefined ? undefined : ruleByCode.get(code);
      if (!rule) {
        const oldRule = oldRules.find((entry) => entry.id === item.ruleId);
        convertedItems += 1;
        inventory.push({
          id: item.id,
          ruleId: null,
          quantity: item.quantity,
          equipped: item.equipped,
          durabilityLeft: item.durabilityLeft ?? null,
          note: item.note ?? null,
          name: oldRule?.name ?? item.ruleId,
          description: oldRule?.description ?? null,
          modifierRuleIds: item.modifierRuleIds ?? [],
        });
        continue;
      }
      const modifierRuleIds = (item.modifierRuleIds ?? [])
        .map((id) => {
          const modifierCode = codeById.get(id) ?? newCodeById.get(id);
          const modifier = modifierCode === undefined ? undefined : ruleByCode.get(modifierCode);

          return modifier?.id;
        })
        .filter((id): id is string => Boolean(id));
      inventory.push({ ...item, ruleId: rule.id, modifierRuleIds });
    }

    const states = [];
    for (const state of version.states) {
      const newId = remap(state.stateRuleId, false, oldNameOf(state.stateRuleId));
      if (newId === null) continue;
      states.push({ ...state, stateRuleId: newId });
    }

    const senses = [];
    for (const sense of version.senses) {
      const newId = remap(sense.ruleId, false, oldNameOf(sense.ruleId));
      if (newId === null) continue;
      const newRule = newRules.find((rule) => rule.id === newId);
      const senseSpec = newRule?.type === 'sense' ? (newRule.spec as SenseSpec | undefined) : undefined;
      senses.push({
        ...sense,
        ruleId: newId,
        status: sense.status ?? senseSpec?.status ?? 'precise',
        radius: sense.radius ?? senseSpec?.radius ?? { base: 0, size: 0 },
      });
    }

    const migrated: CharacterVersion = {
      ...version,
      spaceCode: input.newSpaceCode,
      rulesRevision: input.newRevision,
      raceRuleId,
      characteristics,
      resources,
      abilities: remappedAbilities,
      inventory,
      states,
      senses,
      points: { ...version.points, orTotal: input.effectiveLimits.orTotal },
      budgets: { osTotal: input.effectiveLimits.osTotal, moneyBudget: input.effectiveLimits.moneyBudget },
    };

    // Способности с невыполненными требованиями сбрасываются (конфликт-редактор открывается уже без них).
    const droppedAbilities = this.unmetAbilities(migrated, input);
    const droppedCodes = new Set(droppedAbilities.map((entry) => entry.code));
    const finalAbilities = migrated.abilities.filter(
      (ability) => !droppedCodes.has(codeById.get(ability.ruleId) ?? ability.ruleId),
    );
    for (const dropped of droppedAbilities) {
      problems.push({ kind: 'unmetRequirement', label: dropped.label, detail: dropped.reason });
    }

    const finalVersion: CharacterVersion = { ...migrated, abilities: finalAbilities };

    const config = this.configOf(input.effectiveLimits);
    const oldModel = characterEditorService.build(
      characterBuildService.fromVersion(version, input.oldSpaceId, oldRules),
      oldRules,
      config,
      input.keywords ?? [],
      input.mechanics ?? [],
    );
    const newModel = characterEditorService.build(
      characterBuildService.fromVersion(finalVersion, input.newSpaceId, newRules),
      newRules,
      config,
      input.keywords ?? [],
      input.mechanics ?? [],
    );

    const abilityChanges = this.abilityChanges(oldModel, newModel, oldRules, newRules, codeById, newCodeById);
    const diffs = this.diffsOf(oldModel, newModel, codeById, newCodeById, abilityChanges);

    // Хранящиеся характеристики, которые новая модель не может вывести (раса не даёт базу) —
    // персонаж их потеряет в редакторе. Если теряется вся база — раса некорректна, сбрасываем её.
    const lostProblems = this.lostCharacteristicProblems(finalVersion, newModel, oldRules, newCodeById, newRules);
    const brokenRace = lostProblems.some((problem) => problem.kind === 'raceBroken');
    const finalWithRace = brokenRace ? { ...finalVersion, raceRuleId: null } : finalVersion;
    problems.push(...lostProblems);

    const budgetOverruns: MigrationProblem[] = [];
    const budgets: { label: string; budget: { exceeded: boolean } }[] = [
      { label: 'ОС', budget: newModel.budgets.os },
      { label: 'ОЛ', budget: newModel.budgets.ol },
      { label: 'ОР', budget: newModel.budgets.or },
      { label: 'Деньги', budget: newModel.budgets.money },
    ];
    for (const entry of budgets) {
      if (entry.budget.exceeded)
        budgetOverruns.push({ kind: 'budgetOverrun', label: entry.label, detail: 'Превышен реальный лимит' });
    }

    const allProblems = [...problems, ...budgetOverruns];
    const hasConflicts = allProblems.length > 0;
    const kind: MigrationKind = hasConflicts
      ? 'conflicts'
      : diffs.length > 0 || abilityChanges.length > 0 || convertedItems > 0
        ? 'resolved'
        : 'ok';

    return {
      kind,
      version: finalWithRace,
      problems: allProblems,
      diffs,
      abilities: abilityChanges,
      convertedItems,
    };
  }

  /**
   * Живое сравнение при разрешении конфликта (конфликт-редактор): исходная версия персонажа
   * против ТЕКУЩЕГО черновика. Не ремапит и не сбрасывает — показывает, что изменилось по мере
   * правок. Проблемы — только перерасход бюджетов (важно для «Готов»).
   */
  compareCurrent(
    originalVersion: CharacterVersion,
    draftBuild: CharacterBuild,
    oldRules: Rule[],
    newRules: Rule[],
    effectiveLimits: { osTotal: number | null; orTotal: number | null; moneyBudget: number | null },
    keywords?: Keyword[],
    mechanics?: Mechanic[],
  ): MigrationResult {
    const config = this.configOf(effectiveLimits);
    const oldCodeById = new Map(oldRules.map((rule) => [rule.id, rule.code]));
    const newCodeById = new Map(newRules.map((rule) => [rule.id, rule.code]));
    const oldModel = characterEditorService.build(
      characterBuildService.fromVersion(originalVersion, oldRules[0]?.spaceId ?? 0, oldRules),
      oldRules,
      config,
      keywords ?? [],
      mechanics ?? [],
    );
    const newModel = characterEditorService.build(draftBuild, newRules, config, keywords ?? [], mechanics ?? []);
    const abilities = this.abilityChanges(oldModel, newModel, oldRules, newRules, oldCodeById, newCodeById);
    const diffs = this.diffsOf(oldModel, newModel, oldCodeById, newCodeById, abilities);

    const overruns: MigrationProblem[] = [];
    const budgets: { label: string; budget: { exceeded: boolean } }[] = [
      { label: 'ОС', budget: newModel.budgets.os },
      { label: 'ОЛ', budget: newModel.budgets.ol },
      { label: 'ОР', budget: newModel.budgets.or },
      { label: 'Деньги', budget: newModel.budgets.money },
    ];
    for (const entry of budgets) {
      if (entry.budget.exceeded)
        overruns.push({ kind: 'budgetOverrun', label: entry.label, detail: 'Превышен реальный лимит' });
    }

    const version = characterEditorService.toVersion(draftBuild, newRules, config, keywords ?? [], mechanics ?? []);
    const kind: MigrationKind =
      overruns.length > 0 ? 'conflicts' : diffs.length > 0 || abilities.length > 0 ? 'resolved' : 'ok';

    return { kind, version, problems: overruns, diffs, abilities, convertedItems: 0 };
  }

  /** Потерянные характеристики: хранятся в версии, но отсутствуют в новой модели. */
  private lostCharacteristicProblems(
    finalVersion: CharacterVersion,
    newModel: CharacterEditorModel,
    oldRules: Rule[],
    newCodeById: Map<string, string>,
    newRules: Rule[],
  ): MigrationProblem[] {
    const newCodes = new Set(newModel.characteristics.map((characteristic) => characteristic.code));
    const lost: MigrationProblem[] = [];
    for (const characteristic of finalVersion.characteristics) {
      const code = newCodeById.get(characteristic.ruleId);
      if (!code || newCodes.has(code)) continue;
      const rule = oldRules.find((entry) => entry.id === characteristic.ruleId);
      lost.push({
        kind: 'lostCharacteristic',
        label: rule?.name ?? characteristic.ruleId,
        detail: 'Исчезнет: раса не предоставляет базовую характеристику в новой версии',
      });
    }

    // Раса некорректна, если не даёт базы вообще (в спеке нет характеристик) — сбрасываем её.
    if (lost.length > 0 && finalVersion.raceRuleId !== null && newModel.race.name !== null) {
      const raceRule = newRules.find((rule) => rule.id === finalVersion.raceRuleId);
      const spec =
        raceRule?.type === 'race' ? (raceRule.spec as { characteristics?: unknown[] } | undefined) : undefined;
      if (!spec || (spec.characteristics ?? []).length === 0) {
        lost.push({
          kind: 'raceBroken',
          label: newModel.race.name,
          detail: 'Раса некорректна — не предоставляет базовых характеристик; выберите её заново',
        });
      }
    }

    return lost;
  }

  private configOf(effectiveLimits: MigrationInput['effectiveLimits']): CharacterCreationConfig {
    return {
      osTotal: effectiveLimits.osTotal,
      orTotal: effectiveLimits.orTotal,
      moneyBudget: effectiveLimits.moneyBudget,
    };
  }

  /** Способности с невыполненными требованиями (кроме automatic/gifted/derived) + причина. */
  private unmetAbilities(
    version: CharacterVersion,
    input: MigrationInput,
  ): { code: string; label: string; reason: string | null }[] {
    const model = characterEditorService.build(
      characterBuildService.fromVersion(version, input.newSpaceId, input.newRules),
      input.newRules,
      this.configOf(input.effectiveLimits),
      input.keywords ?? [],
      input.mechanics ?? [],
    );
    const result: { code: string; label: string; reason: string | null }[] = [];
    for (const ability of model.abilities) {
      if (ability.automatic || ability.gifted || ability.derived) continue;
      if (ability.multiple) {
        for (const instance of ability.instances) {
          if (instance.level <= 0) continue;
          const failed = instance.levels.slice(0, instance.level).find((entry) => !entry.met);
          if (failed)
            result.push({ code: ability.code, label: `${ability.name} (${instance.domain})`, reason: failed.reason });
        }
        continue;
      }
      if (ability.level <= 0) continue;
      const failed = ability.levels.slice(0, ability.level).find((entry) => !entry.met);
      if (failed) result.push({ code: ability.code, label: ability.name, reason: failed.reason });
    }

    return result;
  }

  /** Изменения способностей: сброшены (старая → нет), пересчитаны, добавлены. */
  private abilityChanges(
    oldModel: CharacterEditorModel,
    newModel: CharacterEditorModel,
    oldRules: Rule[],
    newRules: Rule[],
    oldCodeById: Map<string, string>,
    newCodeById: Map<string, string>,
  ): MigrationAbilityChange[] {
    const changes: MigrationAbilityChange[] = [];
    const keyOf = (code: string, domain?: string): string => (domain ? `${code}|${domain}` : code);

    const oldKeys = new Set<string>();
    for (const ability of oldModel.abilities) {
      if (ability.multiple) {
        for (const instance of ability.instances) {
          if (instance.level <= 0) continue;
          oldKeys.add(keyOf(ability.code, instance.domain));
        }
        continue;
      }
      if (ability.level > 0) oldKeys.add(keyOf(ability.code));
    }
    const newKeys = new Set<string>();
    for (const ability of newModel.abilities) {
      if (ability.multiple) {
        for (const instance of ability.instances) {
          if (instance.level <= 0) continue;
          newKeys.add(keyOf(ability.code, instance.domain));
        }
        continue;
      }
      if (ability.level > 0) newKeys.add(keyOf(ability.code));
    }

    const oldByCode = new Map(oldModel.abilities.map((ability) => [ability.code, ability]));
    const newByCode = new Map(newModel.abilities.map((ability) => [ability.code, ability]));

    /** Значения параметров способности (код → число) — для параметрической/табличной цены и подписи. */
    const paramValuesOf = (ability: EditorAbility | undefined): Map<string, number> => {
      const result = new Map<string, number>();
      for (const parameter of ability?.parameters ?? []) result.set(parameter.code, parameter.value.base);

      return result;
    };

    /** Подпись способности: имя правила с подставленными значениями параметров («Врождённая Сила X» → «Врождённая Сила 2»). */
    const labelOf = (ability: EditorAbility | undefined, domain?: string): string => {
      if (!ability) return domain ?? '?';
      let name = ability.name;
      for (const parameter of ability.parameters) {
        if (parameter.value.base === 0 && parameter.value.size === 0) continue;
        name = name.replace(parameter.label, DimensionalNumber.from(parameter.value).toString());
      }

      return domain ? `${name} (${domain})` : name;
    };

    /** Стоимость способности в выбранной зоне с учётом параметров (array/progression/parameter/parameter_table). */
    const ruleCostOf = (
      rules: Rule[],
      ruleId: string,
      level: number,
      parameters: Map<string, number>,
      zone?: string,
    ): { zone: string; cost: number } | null => {
      const rule = rules.find((entry) => entry.id === ruleId);
      const spec = rule?.spec as AbilitySpec | undefined;
      if (!spec || spec.type === 'group') return null;
      const zones = spec.zones as Partial<Record<string, AbilityCost>> | undefined;
      const zoneCode =
        zone ??
        Object.entries(zones ?? {})
          .filter(([, cost]) => cost && cost.kind !== 'automatic')
          .map(([code]) => code)[0] ??
        null;
      if (!zoneCode) return null;
      const cost = zones?.[zoneCode];
      if (!cost) return null;
      if (cost.kind === 'automatic') return { zone: zoneCode, cost: 0 };
      if (cost.kind === 'parameter') {
        return { zone: zoneCode, cost: (parameters.get(cost.parameter_code) ?? 0) * cost.per_unit };
      }
      if (cost.kind === 'parameter_table') {
        return { zone: zoneCode, cost: cost.costs?.[String(parameters.get(cost.parameter_code) ?? 0)] ?? 0 };
      }
      if (cost.kind === 'parameter_sum_tables') {
        let sum = 0;
        for (const [code, table] of Object.entries(cost.tables)) {
          sum += table[String(parameters.get(code) ?? 0)] ?? 0;
        }

        return { zone: zoneCode, cost: sum };
      }
      if (cost.kind === 'progression') {
        let total = 0;
        for (let i = 0; i < level; i++) total += cost.base_cost + cost.step * i;

        return { zone: zoneCode, cost: total };
      }

      return { zone: zoneCode, cost: (cost.levels_cost ?? []).slice(0, level).reduce((sum, value) => sum + value, 0) };
    };

    for (const key of new Set([...oldKeys, ...newKeys])) {
      const [code, domain] = key.split('|');
      const oldAbility = oldByCode.get(code);
      const newAbility = newByCode.get(code);
      const oldLevel = domain
        ? (oldAbility?.instances.find((instance) => instance.domain === domain)?.level ?? 0)
        : (oldAbility?.level ?? 0);
      const newLevel = domain
        ? (newAbility?.instances.find((instance) => instance.domain === domain)?.level ?? 0)
        : (newAbility?.level ?? 0);

      if (oldKeys.has(key) && !newKeys.has(key)) {
        const oldRuleId = this.ruleIdOf(oldModel, code, domain, oldCodeById);
        const cost = oldRuleId === null ? null : ruleCostOf(oldRules, oldRuleId, oldLevel, paramValuesOf(oldAbility));
        changes.push({
          label: labelOf(oldAbility, domain),
          kind: 'removed',
          zone: cost?.zone ?? null,
          costBefore: cost?.cost ?? null,
          costAfter: null,
        });
      } else if (!oldKeys.has(key) && newKeys.has(key)) {
        const newRuleId = this.ruleIdOf(newModel, code, domain, newCodeById);
        const cost = newRuleId === null ? null : ruleCostOf(newRules, newRuleId, newLevel, paramValuesOf(newAbility));
        changes.push({
          label: labelOf(newAbility, domain),
          kind: 'added',
          zone: cost?.zone ?? null,
          costBefore: null,
          costAfter: cost?.cost ?? null,
        });
      } else if (oldKeys.has(key) && newKeys.has(key)) {
        const oldRuleId = this.ruleIdOf(oldModel, code, domain, oldCodeById);
        const newRuleId = this.ruleIdOf(newModel, code, domain, newCodeById);
        if (oldLevel !== newLevel) {
          const oldCost =
            oldRuleId === null ? null : ruleCostOf(oldRules, oldRuleId, oldLevel, paramValuesOf(oldAbility));
          const newCost =
            newRuleId === null ? null : ruleCostOf(newRules, newRuleId, newLevel, paramValuesOf(newAbility));
          changes.push({
            label: labelOf(oldAbility, domain),
            kind: 'changed',
            zone: newCost?.zone ?? oldCost?.zone ?? null,
            costBefore: oldCost?.cost ?? null,
            costAfter: newCost?.cost ?? null,
          });
        } else {
          const oldCost =
            oldRuleId === null ? null : ruleCostOf(oldRules, oldRuleId, oldLevel, paramValuesOf(oldAbility));
          const newCost =
            newRuleId === null ? null : ruleCostOf(newRules, newRuleId, newLevel, paramValuesOf(newAbility));
          if (oldCost?.zone !== newCost?.zone || oldCost?.cost !== newCost?.cost) {
            changes.push({
              label: labelOf(oldAbility, domain),
              kind: 'changed',
              zone: newCost?.zone ?? oldCost?.zone ?? null,
              costBefore: oldCost?.cost ?? null,
              costAfter: newCost?.cost ?? null,
            });
          }
        }
      }
    }

    return changes;
  }

  private ruleIdOf(
    model: CharacterEditorModel,
    code: string,
    _domain: string | undefined,
    _codeById: Map<string, string>,
  ): string | null {
    return model.abilities.find((entry) => entry.code === code)?.ruleId ?? null;
  }

  /** Честный диф по обеим моделям: характеристики, ресурсы, бюджеты. */
  private diffsOf(
    oldModel: CharacterEditorModel,
    newModel: CharacterEditorModel,
    oldCodeById: Map<string, string>,
    newCodeById: Map<string, string>,
    abilities: MigrationAbilityChange[],
  ): MigrationDiffItem[] {
    const diffs: MigrationDiffItem[] = [];

    const oldChars = new Map(oldModel.characteristics.map((characteristic) => [characteristic.code, characteristic]));
    const newChars = new Map(newModel.characteristics.map((characteristic) => [characteristic.code, characteristic]));
    for (const code of new Set([...oldChars.keys(), ...newChars.keys()])) {
      const oldValue = oldChars.get(code);
      const newValue = newChars.get(code);
      if (!oldValue || !newValue) continue;
      const before = dimValue(oldValue.value);
      const after = dimValue(newValue.value);
      if (before === after) continue;
      // Пояснение: значение ограничено потолком снаряжения (следует за другой характеристикой).
      const cap = newValue.modifiers.find((modifier) => modifier.limit);
      diffs.push({
        label: newValue.name,
        before,
        after,
        tone: 'changed',
        explanation: cap?.limitFormula ? `потолок снаряжения: ${cap.limitFormula}` : undefined,
      });
    }

    const oldRes = new Map(
      oldModel.resources.map((resource) => [oldCodeById.get(resource.ruleId) ?? resource.ruleId, resource]),
    );
    const newRes = new Map(
      newModel.resources.map((resource) => [newCodeById.get(resource.ruleId) ?? resource.ruleId, resource]),
    );
    for (const code of new Set([...oldRes.keys(), ...newRes.keys()])) {
      const oldResource = oldRes.get(code);
      const newResource = newRes.get(code);
      if (!oldResource || !newResource) continue;
      const before = `${dimValue(oldResource.current)} / ${dimValue(oldResource.base)}`;
      const after = `${dimValue(newResource.current)} / ${dimValue(newResource.base)}`;
      if (before === after) continue;
      diffs.push({ label: code, before, after, tone: 'changed' });
    }

    const zones: { code: string; label: string; oldSpent: number; newSpent: number; exceeded: boolean }[] = [
      {
        code: 'os',
        label: 'ОС',
        oldSpent: oldModel.budgets.os.spent,
        newSpent: newModel.budgets.os.spent,
        exceeded: newModel.budgets.os.exceeded,
      },
      {
        code: 'ol',
        label: 'ОЛ',
        oldSpent: oldModel.budgets.ol.spent,
        newSpent: newModel.budgets.ol.spent,
        exceeded: newModel.budgets.ol.exceeded,
      },
      {
        code: 'or',
        label: 'ОР',
        oldSpent: oldModel.budgets.or.spent,
        newSpent: newModel.budgets.or.spent,
        exceeded: newModel.budgets.or.exceeded,
      },
      {
        code: 'money',
        label: 'Деньги',
        oldSpent: oldModel.budgets.money.spent,
        newSpent: newModel.budgets.money.spent,
        exceeded: newModel.budgets.money.exceeded,
      },
    ];
    for (const zone of zones) {
      if (zone.oldSpent === zone.newSpent) continue;
      const tone = zone.exceeded ? 'red' : zone.newSpent > zone.oldSpent ? 'changed' : 'green';
      diffs.push({
        label: zone.label,
        before: String(zone.oldSpent),
        after: String(zone.newSpent),
        tone,
        explanation: this.budgetExplanation(zone.code, abilities),
      });
    }

    return diffs;
  }

  /** Пояснение бюджетного сдвига по способностям: сброшенные/пересчитанные. */
  private budgetExplanation(zoneCode: string, abilities: MigrationAbilityChange[]): string | null {
    const parts: string[] = [];
    for (const change of abilities) {
      if (change.zone !== zoneCode || change.zone === null) continue;
      const zone = ZONE_LABEL[change.zone] ?? change.zone;
      if (change.kind === 'removed' && change.costBefore !== null) {
        parts.push(`сброшена «${change.label}» (−${change.costBefore} ${zone})`);
      } else if (
        change.kind === 'changed' &&
        change.costBefore !== null &&
        change.costAfter !== null &&
        change.costBefore !== change.costAfter
      ) {
        parts.push(`«${change.label}»: ${change.costBefore} ${zone} → ${change.costAfter} ${zone}`);
      } else if (change.kind === 'added' && change.costAfter !== null) {
        parts.push(`добавлена «${change.label}» (+${change.costAfter} ${zone})`);
      }
    }
    if (parts.length === 0) return 'Пересчитано по новым правилам';

    return parts.join('; ');
  }
}
