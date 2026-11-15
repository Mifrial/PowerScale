import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { SpellCastPathOption } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastPathOption';
import type { SpellCastSourceOption } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastSourceOption';
import type { SpellCastSpellOption } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastSpellOption';
import { MAGIC_CORE_ITEM_CODE } from '@/modules/Roleplay/Game/Constant/Spell/MAGIC_CORE_ITEM_CODE';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { MagicPathSpec } from '@/modules/Roleplay/Rule/Dto/MagicPath/MagicPathSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { spellCastDifficultyService } from '@/modules/Roleplay/Game/Service/Instance/spellCastDifficultyService';
import { spellDeviationService } from '@/modules/Roleplay/Game/Service/Instance/spellDeviationService';
import type { SpellValue } from '@/modules/Roleplay/Rule/Dto/Ability/SpellValue';
import { aggregateSourceDeltasService, damageTypeSpecService, raceSpecService } from '@/modules/Roleplay/Rule/init';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { ItemStorageValue } from '@/modules/Roleplay/Character/Dto/ItemStorageValue';
import { spellSourcePowerService } from '@/modules/Roleplay/Character/init';
import { magicPathSpecService } from '@/modules/Roleplay/Rule/init';

/** Источники, пути и изученные заклинания для диалога сотворения. */
export class SpellCastOptionsService {
  listOwnedSpells(overview: CharacterOverview | null, rules: Rule[]): SpellCastSpellOption[] {
    if (!overview) {
      return [];
    }
    const seen = new Set<string>();
    const options: SpellCastSpellOption[] = [];
    for (const ability of overview.abilities) {
      if (ability.type !== 'spell' || seen.has(ability.ruleCode)) {
        continue;
      }
      const rule = rules.find((entry) => entry.code === ability.ruleCode);
      if (!rule || !this.isSpellRule(rule)) {
        continue;
      }
      seen.add(ability.ruleCode);
      const spec = spellCastDifficultyService.asSpellAbilitySpec(rule);
      options.push({
        ruleCode: ability.ruleCode,
        name: ability.name,
        requiredPower: spec ? this.fixedSpellValue(spec.spell.power) : null,
        requiredControl: spec ? this.fixedSpellValue(spec.spell.control) : null,
      });
    }

    return options;
  }

  /**
   * Заклинания, записанные в источниках-хранилищах. Для пути записей они
   * доступны без изучения; состав читается из payload строк инвентаря.
   */
  listRecordedSpells(version: CharacterVersion | null, rules: Rule[]): SpellCastSpellOption[] {
    if (!version) {
      return [];
    }
    const seen = new Set<string>();
    const options: SpellCastSpellOption[] = [];
    for (const item of version.inventory) {
      const payload = item.payload ?? {};
      const recorded = payload.recorded_spells;
      if (!Array.isArray(recorded)) continue;
      for (const entry of recorded) {
        const code = entry && typeof entry === 'object' && 'ruleCode' in entry ? entry.ruleCode : null;
        if (typeof code !== 'string' || seen.has(code)) continue;
        const rule = rules.find((row) => row.code === code);
        if (!rule || !this.isSpellRule(rule)) continue;
        seen.add(code);
        const spec = spellCastDifficultyService.asSpellAbilitySpec(rule);
        options.push({
          ruleCode: code,
          name: rule.name,
          requiredPower: spec ? this.fixedSpellValue(spec.spell.power) : null,
          requiredControl: spec ? this.fixedSpellValue(spec.spell.control) : null,
        });
      }
    }

    return options;
  }

  listSources(version: CharacterVersion | null, rules: Rule[], keywords: Keyword[] = []): SpellCastSourceOption[] {
    if (!version) {
      return [];
    }
    const options: SpellCastSourceOption[] = [];
    for (const item of version.inventory) {
      const key = `inventory:${item.id}`;
      const rule = rules.find((entry) => entry.code === item.ruleCode);
      const spec = rule?.spec as ItemSpec | undefined;
      const equipped = item.equipped ? '' : ' (не экипирован)';
      if (spec?.storage) {
        const storage = version.items?.find((entry) => entry.inventoryId === item.id);
        const power = storage?.maxPower ?? item.parameters?.[spec.storage.power_parameter];
        const powerLabel = power ? ` ${DimensionalNumber.from(power).toString()}` : '';
        const manaLabel = storage ? ` · мана ${storage.mana}/${storage.manaMax}` : '';
        options.push({
          key,
          name: `${rule?.name ?? 'Кристалл'}${powerLabel}${manaLabel}${equipped}`,
        });
        continue;
      }

      if (item.ruleCode !== MAGIC_CORE_ITEM_CODE) {
        continue;
      }

      const serverPower = this.serverSourcePower(version, key, 'magic-power');
      const power = serverPower?.value ?? item.parameters?.power;
      const powerLabel = power ? ` ${DimensionalNumber.from(power).toString()}` : '';
      const bonus = serverPower ? 0 : this.sourceBonus(version, rules, keywords, key);
      const bonusLabel = bonus > 0 ? ` (+${bonus})` : '';
      options.push({
        key,
        name: `${rule?.name ?? 'Магическое ядро'}${powerLabel}${bonusLabel}${equipped}`,
      });
    }

    return options;
  }

  listPaths(version: CharacterVersion | null, rules: Rule[]): SpellCastPathOption[] {
    if (!version) {
      return [];
    }
    const codes = new Set<string>();
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    const addPaths = (rule: Rule | undefined, level: number): void => {
      if (
        !rule ||
        !['ability', 'trait'].includes(rule.type) ||
        !rule.spec ||
        typeof rule.spec !== 'object' ||
        !('grants' in rule.spec)
      ) {
        return;
      }
      const rows = this.grantRows(rule.spec);
      for (const levelGrant of rows) {
        if (levelGrant.level > level) {
          continue;
        }
        for (const grant of levelGrant.grants) {
          if (grant.type === 'magic_path' && grant.path_code) {
            codes.add(grant.path_code);
          }
        }
      }
    };
    for (const ability of version.abilities) {
      addPaths(byCode.get(ability.ruleCode), ability.level);
    }
    // Автоматические ссылки расы («Даёт способности») дают пути без выбранного уровня:
    // кристалл Яппи открывает «Простейшее волшебство» сразу.
    for (const ref of raceSpecService.automaticAbilities(version.raceRuleCode ?? null, byCode)) {
      addPaths(byCode.get(ref.ability_code), 1);
    }
    const options: SpellCastPathOption[] = [];
    for (const pathCode of codes) {
      const rule = rules.find((entry) => entry.code === pathCode && entry.type === 'magic_path');
      const spec = this.asMagicPathSpec(rule);
      options.push({
        pathCode,
        name: rule?.name ?? pathCode,
        checkCode: spec?.check_code ?? null,
        castCheckCode: spec?.cast_check_code ?? null,
        powerCharacteristicCode: spec?.power_characteristic_code ?? null,
        controlCharacteristicCode: spec?.control_characteristic_code ?? null,
        noControl: spec?.no_control === true,
        recordedCasting: spec?.recorded_casting === true,
        activeSpellLimit: spec?.active_spell_limit ?? null,
        includesPathCodes: spec?.includes_path_codes ?? [],
      });
    }

    return options;
  }

  /**
   * Путь по умолчанию для заклинания: включающий путь раньше собственного.
   *
   * Псионическое заклинание у Шамана подставляется как шаманское (SR-071);
   * если включающего пути нет, остаётся путь, на котором заклинание изучено.
   */
  defaultPathForSpell(
    spellCode: string,
    paths: readonly SpellCastPathOption[],
    abilities: readonly CharacterAbility[],
  ): string | null {
    const domain = abilities.find(
      (ability) => ability.ruleCode === spellCode && ability.level >= 1 && ability.domainCode,
    )?.domainCode;
    if (!domain) return null;

    const covers = (owner: string): boolean => {
      const seen = new Set<string>();
      const queue = [owner];
      while (queue.length > 0) {
        const current = queue.shift();
        if (!current || seen.has(current)) continue;
        if (current === domain) return true;
        seen.add(current);
        queue.push(...(paths.find((path) => path.pathCode === current)?.includesPathCodes ?? []));
      }

      return false;
    };
    const covering = paths.filter((path) => covers(path.pathCode));

    return covering.find((path) => path.pathCode !== domain)?.pathCode ?? covering[0]?.pathCode ?? null;
  }

  /** Характеристика листа или null: подставлять «3» вместо отсутствующего стата нельзя. */
  characteristicValue(overview: CharacterOverview | null, ruleCode: string | null): DimensionalNumberValue | null {
    if (!ruleCode) return null;

    return overview?.characteristics.find((entry) => entry.ruleCode === ruleCode)?.value ?? null;
  }

  defaultUsedPower(
    overview: CharacterOverview | null,
    states: CharacterVersion['states'] = [],
    sourceKey = '',
    characteristicCode: string | null = null,
    rules: Rule[] = [],
  ): DimensionalNumberValue | null {
    const base = this.characteristicValue(overview, characteristicCode);
    if (!base) return null;

    return spellDeviationService.penalizeUsedPower(
      base,
      spellDeviationService.strengthOnSource(states, sourceKey, rules),
    );
  }

  /**
   * Потолок мощи выбранного источника: мощь экземпляра, бонус формы и штраф отклонения.
   *
   * Порядок как у серверного `GameSpellSourcePower`: `sheet.sourcePowers`,
   * Предельная мощь хранилища из `sheet.items`, параметр экземпляра, затем
   * характеристика листа для legacy-источников. Нет стата — null, не `{3|0}`.
   */
  sourceCapOf(
    version: CharacterVersion | null,
    overview: CharacterOverview | null,
    rules: Rule[],
    keywords: Keyword[],
    sourceKey: string,
    characteristicCode: string | null,
  ): DimensionalNumberValue | null {
    const serverPower = this.serverSourcePower(version, sourceKey, characteristicCode);
    const base =
      serverPower?.value ??
      this.storagePower(version, sourceKey) ??
      this.itemPower(version, rules, sourceKey, characteristicCode) ??
      this.characteristicValue(overview, characteristicCode);
    if (!base) return null;
    const hasServerPowers = (version?.sourcePowers?.length ?? 0) > 0;
    const isInventory = sourceKey.startsWith('inventory:');
    const bonus =
      serverPower || (hasServerPowers && !isInventory) ? 0 : this.sourceBonus(version, rules, keywords, sourceKey);
    const value = bonus === 0 ? base : CharacteristicNumber.from(base).modifyWith(bonus).value;

    return spellDeviationService.penalizeUsedPower(
      value,
      spellDeviationService.strengthOnSource(version?.states ?? [], sourceKey, rules),
    );
  }

  /**
   * Мана каста из предмета-хранилища: `мощь × ceil(Создание в ОД / лимит ОД хода)`.
   * Ядро маны не тратит, поэтому для него вызывающий цену не считает.
   */
  manaCostOf(power: DimensionalNumberValue | null, creationAp: number, turnApMax: number): number {
    if (!power || creationAp <= 0 || turnApMax <= 0) {
      return 0;
    }

    return DimensionalNumber.from(power).toNumber() * Math.ceil(creationAp / turnApMax);
  }

  /** Рантайм хранилища по ключу источника (`inventory:<id>`). */
  storageOf(version: CharacterVersion | null, sourceKey: string): ItemStorageValue | null {
    if (!version?.items?.length) {
      return null;
    }
    const [kind, raw = ''] = sourceKey.split(':', 2);
    if (kind !== 'inventory' || Number(raw) <= 0) {
      return null;
    }
    const inventoryId = Number(raw);

    return version.items.find((entry) => entry.inventoryId === inventoryId) ?? null;
  }

  /** Эффективная мощь источника из серверного листа. */
  private serverSourcePower(
    version: CharacterVersion | null,
    sourceKey: string,
    characteristicCode: string | null,
  ): { value: DimensionalNumberValue; bonus: number } | null {
    if (!version?.sourcePowers?.length || !characteristicCode) return null;
    const [kind, raw = ''] = sourceKey.split(':', 2);
    if (kind !== 'inventory' || Number(raw) <= 0) return null;
    const inventoryId = Number(raw);
    const row = version.sourcePowers.find(
      (entry) => entry.inventoryId === inventoryId && entry.characteristicCode === characteristicCode,
    );

    return row ? { value: row.value, bonus: row.bonus } : null;
  }

  /** Предельная мощь предмета-хранилища из `sheet.items` — собственный стат источника. */
  private storagePower(version: CharacterVersion | null, sourceKey: string): DimensionalNumberValue | null {
    return this.storageOf(version, sourceKey)?.maxPower ?? null;
  }

  /** Мощь предмета-источника из параметра экземпляра. */
  private itemPower(
    version: CharacterVersion | null,
    rules: Rule[],
    sourceKey: string,
    characteristicCode: string | null,
  ): DimensionalNumberValue | null {
    if (!version || !characteristicCode) return null;
    const [kind, raw = ''] = sourceKey.split(':', 2);
    if (kind !== 'inventory' || Number(raw) <= 0) return null;
    const inventoryId = Number(raw);
    const item = version.inventory.find((row) => row.id === inventoryId);
    if (!item || item.ruleCode === null) return null;
    const rule = rules.find((entry) => entry.code === item.ruleCode && entry.type === 'item');
    const spec = rule?.spec as ItemSpec | undefined;
    if (!spec) return null;
    for (const grant of spec.grants ?? []) {
      if (grant.type !== 'characteristic_parameter' || grant.characteristic_code !== characteristicCode) continue;
      const value = item.parameters?.[grant.parameter_code];
      if (value) return value;
    }

    return null;
  }

  /** Сумма бонусов `spell_source_power` подходящих способностей. */
  private sourceBonus(version: CharacterVersion | null, rules: Rule[], keywords: Keyword[], sourceKey: string): number {
    if (!version) return 0;
    const levels = spellSourcePowerService.effectiveLevels(version.abilities, rules, keywords);
    const characteristicValues = new Map<string, DimensionalNumberValue>();
    for (const entry of version.characteristics ?? []) {
      if (entry.value) characteristicValues.set(entry.ruleCode, entry.value);
    }

    return spellSourcePowerService.bonus({
      rules,
      keywords,
      abilities: version.abilities,
      levels,
      characteristicValues,
      inventory: version.inventory,
      sourceKey,
    });
  }

  defaultControl(overview: CharacterOverview | null, characteristicCode: string | null): DimensionalNumberValue | null {
    return this.characteristicValue(overview, characteristicCode);
  }

  targetResistanceFromOverview(target: CharacterOverview | null, damageTypeCode: string): number {
    return this.resistanceFromOverview(target, (code) => code === damageTypeCode);
  }

  /** Сопротивление магии: все типы урона, меняющие сложность сотворения. */
  targetSpellResistanceFromOverview(target: CharacterOverview | null, rules: Rule[]): number {
    const magicTypes = new Set(
      rules
        .filter(
          (rule) =>
            rule.type === 'damage_type' &&
            damageTypeSpecService.asDamageTypeSpec(rule)?.modifies_spell_difficulty === true,
        )
        .map((rule) => rule.code),
    );
    if (magicTypes.size === 0) {
      return 0;
    }

    return this.resistanceFromOverview(target, (code) => code !== null && magicTypes.has(code));
  }

  private resistanceFromOverview(
    target: CharacterOverview | null,
    accepts: (damageTypeCode: string | null) => boolean,
  ): number {
    if (!target?.defense) return 0;
    const fromArmor = target.defense.armor.flatMap((armor) =>
      armor.lines
        .filter((line) => line.kind === 'resistance' && accepts(line.damageTypeCode))
        .map((line) => ({ source_code: line.sourceCode ?? armor.itemRuleCode, delta: line.value })),
    );
    const fromGrants = (target.defense.resistances ?? [])
      .filter((line) => line.kind === 'resistance' && accepts(line.damageTypeCode))
      .map((line) => ({ source_code: line.sourceCode, delta: line.value }));

    return aggregateSourceDeltasService.netSourceDelta([...fromGrants, ...fromArmor]);
  }

  private grantRows(spec: object): { level: number; grants: Grant[] }[] {
    if (!('grants' in spec) || !Array.isArray(spec.grants)) {
      return [];
    }

    return spec.grants as { level: number; grants: Grant[] }[];
  }

  private fixedSpellValue(value: SpellValue): DimensionalNumberValue | null {
    if (!('base' in value)) {
      return null;
    }

    return { base: value.base, size: value.size };
  }

  private isSpellRule(rule: Rule): boolean {
    return Boolean(
      rule.type === 'ability' &&
      rule.spec &&
      typeof rule.spec === 'object' &&
      'type' in rule.spec &&
      rule.spec.type === 'spell',
    );
  }

  private asMagicPathSpec(rule: Rule | undefined): MagicPathSpec | null {
    if (!rule || rule.type !== 'magic_path' || !rule.spec || typeof rule.spec !== 'object') {
      return null;
    }

    return magicPathSpecService.resolve(rule.spec);
  }
}
