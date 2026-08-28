import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Снимок персонажа для оценки требований способностей (Requirement). */
export interface CharacterSnapshot {
  /** Уровни способностей по кодам правил. */
  abilityLevels: Map<string, number>;
  /** Признаки (ключевые слова) каждой способности по её коду (для has_ability_keyword). */
  abilityKeywords: Map<string, Set<string>>;
  /**
   * Экземпляры множественных навыков по кодам правил (для домен-скоупированных has_ability
   * требований: «Письменность того же языка»). Пусто, если экземпляров нет.
   */
  abilityInstances?: Map<string, { domain: string; domainCode: string | null; level: number }[]>;
  /**
   * Уровни владения оружием по семьям: код семьи → максимальный уровень владения.
   * Заполняется из «Владение оружием» (domain_ref 'weapon-family') для мин_weapon_mastery.
   */
  weaponProficiencyLevels?: Map<string, number>;
  /** Тэги → семейства оружия с этим тэгом: «копьё» → Set{fam-kopyo, fam-metatelnoe-kopjo}. */
  weaponFamilyTags?: Map<string, Set<string>>;
  /** Значения характеристик по кодам. */
  characteristicValues: Map<string, DimensionalNumberValue>;
  /** Лимиты ресурсов по кодам (число или размерное). */
  resourceLimits: Map<string, number | DimensionalNumberValue>;
  /** Признаки (ключевые слова) персонажа по кодам. */
  keywordCodes: Set<string>;
  /** Имена способностей по кодам (для человекочитаемых причин требований). */
  abilityNames?: Map<string, string>;
  /** Имена признаков по кодам. */
  keywordNames?: Map<string, string>;
  /** Имена характеристик по кодам. */
  characteristicNames?: Map<string, string>;
  /** Имена ресурсов по кодам. */
  resourceNames?: Map<string, string>;
  currentSpeed?: {
    horizontal: { stepsPerActionPoint: number; direction: string | null };
    vertical: { stepsPerActionPoint: number; direction: string | null };
  };
}
