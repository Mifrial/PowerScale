import { keywordExperienceService } from '@/modules/Roleplay/Character/Service/Instance/keywordExperienceService';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemStorage } from '@/modules/Roleplay/Rule/Dto/Item/ItemStorage';

/** Запись заклинания в предмете-хранилище: карточка и записанные улучшения. */
export interface RecordedSpellEntry {
  ruleCode: string;
  upgrades?: string[];
}

/** Разбор записей заклинаний в предмете-хранилище. */
export class ItemStorageService {
  /** Объём элемента со Стоимостью 1. */
  static readonly COST_BASE = 10;

  /** Объём одного элемента по Стоимости: 1 → 10, 2 → 20, 3 → 40 и далее удвоением. */
  volumeOfCost(cost: number): number {
    if (cost <= 0) {
      return 0;
    }

    return ItemStorageService.COST_BASE * Math.pow(2, cost - 1);
  }

  /** Стоимость карточки: первый уровень первой платной зоны; 0 — зоны нет. */
  cardCost(rule: Rule | undefined): number {
    if (!rule || rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object') {
      return 0;
    }
    const spec = rule.spec as AbilitySpec;
    if (spec.type === 'group') {
      return 0;
    }
    const zoneCode = keywordExperienceService.purchasableZoneOf(spec);
    if (!zoneCode) {
      return 0;
    }
    const zone = spec.zones?.[zoneCode];
    if (!zone) {
      return 0;
    }

    return keywordExperienceService.totalCostAtLevel(zone, 1);
  }

  /** Объём карточки по коду или 0, если карточки нет. */
  cardVolume(rules: Rule[], code: string): number {
    return this.volumeOfCost(this.cardCost(rules.find((rule) => rule.code === code)));
  }

  /** Объём записей: заклинания и их улучшения занимают место независимо. */
  occupied(recorded: readonly RecordedSpellEntry[], rules: Rule[]): number {
    let volume = 0;
    for (const entry of recorded) {
      volume += this.cardVolume(rules, entry.ruleCode);
      for (const upgrade of entry.upgrades ?? []) {
        volume += this.cardVolume(rules, upgrade);
      }
    }

    return volume;
  }

  /** Лимит маны: свободный Объём. */
  manaLimit(volume: number, occupied: number): number {
    return Math.max(0, volume - occupied);
  }

  /** Записи из payload строки инвентаря. */
  recordedOf(
    payload: Record<string, unknown> | undefined,
    storage: ItemStorage | null | undefined,
  ): RecordedSpellEntry[] {
    const key = storage?.recorded_parameter;
    if (!key || !payload) {
      return [];
    }
    const raw = payload[key];
    if (!Array.isArray(raw)) {
      return [];
    }

    const entries: RecordedSpellEntry[] = [];
    for (const row of raw) {
      if (!row || typeof row !== 'object' || !('ruleCode' in row)) continue;
      const code = (row as { ruleCode?: unknown }).ruleCode;
      if (typeof code !== 'string' || code === '') continue;
      const upgrades =
        'upgrades' in row && Array.isArray((row as { upgrades?: unknown }).upgrades)
          ? (row as { upgrades: unknown[] }).upgrades.filter((entry): entry is string => typeof entry === 'string')
          : [];
      entries.push(upgrades.length > 0 ? { ruleCode: code, upgrades } : { ruleCode: code });
    }

    return entries;
  }

  /** Добавляет запись, если она влезает в Объём; иначе возвращает прежний состав. */
  withRecorded(
    recorded: readonly RecordedSpellEntry[],
    code: string,
    volume: number,
    rules: Rule[],
  ): RecordedSpellEntry[] {
    if (!code || recorded.some((entry) => entry.ruleCode === code)) {
      return [...recorded];
    }
    const next = [...recorded, { ruleCode: code }];
    if (this.occupied(next, rules) > volume) {
      return [...recorded];
    }

    return next;
  }

  /** Убирает запись по коду. */
  withoutRecorded(recorded: readonly RecordedSpellEntry[], code: string): RecordedSpellEntry[] {
    return recorded.filter((entry) => entry.ruleCode !== code);
  }

  /** Улучшения, привязанные к заклинанию: записываются в кристалл вместе с ним. */
  applicableUpgrades(spellCode: string, rules: Rule[]): Rule[] {
    return rules
      .filter((rule) => {
        if (rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object') return false;
        const spec = rule.spec as {
          parent_ability_code?: string | null;
          spell_upgrade?: { chain?: unknown; charge_cap?: unknown } | null;
        };
        if (!spec.spell_upgrade) return false;
        if ((spec.parent_ability_code ?? null) !== spellCode) return false;

        // Цепь и заряды runtime не исполняет: записывать их в кристалл незачем.
        return !spec.spell_upgrade.chain && !spec.spell_upgrade.charge_cap;
      })
      .sort((left, right) => left.name.localeCompare(right.name));
  }

  /** Добавляет улучшение к записи заклинания, если оно влезает в Объём. */
  withUpgrade(
    recorded: readonly RecordedSpellEntry[],
    spellCode: string,
    upgradeCode: string,
    volume: number,
    rules: Rule[],
  ): RecordedSpellEntry[] {
    const index = recorded.findIndex((entry) => entry.ruleCode === spellCode);
    if (index < 0) return [...recorded];
    const upgrades = recorded[index].upgrades ?? [];
    if (upgrades.includes(upgradeCode)) return [...recorded];
    const next = recorded.map((entry, position) =>
      position === index ? { ruleCode: entry.ruleCode, upgrades: [...upgrades, upgradeCode] } : entry,
    );

    return this.occupied(next, rules) > volume ? [...recorded] : next;
  }

  /** Убирает улучшение из записи заклинания. */
  withoutUpgrade(
    recorded: readonly RecordedSpellEntry[],
    spellCode: string,
    upgradeCode: string,
  ): RecordedSpellEntry[] {
    return recorded.map((entry) => {
      if (entry.ruleCode !== spellCode) return entry;
      const upgrades = (entry.upgrades ?? []).filter((code) => code !== upgradeCode);

      return upgrades.length > 0 ? { ruleCode: entry.ruleCode, upgrades } : { ruleCode: entry.ruleCode };
    });
  }

  /** Карточки-заклинания, которые можно записать: тип spell с платной зоной. */
  recordableSpells(rules: Rule[], storage?: ItemStorage | null, keywordCodeById?: ReadonlyMap<number, string>): Rule[] {
    const allowed = storage?.recorded_keywords ?? [];

    return rules
      .filter((rule) => {
        if (rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object') return false;
        if ((rule.spec as AbilitySpec).type !== 'spell') return false;
        if (this.cardCost(rule) <= 0) return false;
        if (allowed.length === 0) return true;
        if (!keywordCodeById) return false;

        return this.keywordCodesOf(rule, keywordCodeById).some((code) => allowed.includes(code));
      })
      .sort((left, right) => left.name.localeCompare(right.name));
  }

  /** Коды признаков правила по словарю признаков. */
  keywordCodesOf(rule: Rule, keywordCodeById: ReadonlyMap<number, string>): string[] {
    return (rule.keywordIds ?? [])
      .map((id) => keywordCodeById.get(id))
      .filter((code): code is string => typeof code === 'string' && code !== '');
  }
}
