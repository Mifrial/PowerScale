import type { ZoneId } from '@/modules/Roleplay/Rule/Dto/Ability/ZoneId';
import type { AbilityCost } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityCost';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';

/** Общие поля способности (не типоспецифичные). */
export interface AbilitySpecBase {
  zones: Partial<Record<ZoneId, AbilityCost>>;
  requirements: { level: number; requirements: Requirement[] }[];
  grants: { level: number; grants: Grant[] }[];
  parent_ability_code: string | null;
  /** Для способностей владения оружием — код предмета-оружия (напр. «sword»). */
  weapon_item_code?: string | null;
  /** Параметры «X»: подстановка `{code}` в цене/дарах/описании (Дискуссия 2). */
  parameters?: AbilityParameter[];
  /** Код группирующего правила (type 'group'), в которое входит способность («часть группы»). */
  group_code?: string | null;
  /** Раздел каталога Развития (фильтр «Раздел»). Не признак keyword. */
  section?: string | null;
  /**
   * Множественный навык (напр. «Владение языком», «Знание о животных»): изучается по домену
   * (значение выбирается при покупке — из справочника или кастомный текст).
   */
  multiple?: boolean;
  /**
   * Код справочника домена для множественного навыка (напр. 'language' | 'region' | 'species' |
   * 'culture' | 'subject' | 'instrument'). Заполняется вместе с `multiple`.
   */
  domain_ref?: string | null;
  /**
   * Агрегат «Развитие X» (D108): бесплатный навык-агрегатор. Уровень агрегата определяется
   * по числу взятых навыков с признаком method_keyword: уровень N достигнут, если для каждой
   * ступени стоимости 1..N есть минимум `levels[N-1]` методов со стоимостью ≥ N (без пересечения).
   * Бонус уровня раздаётся даром characteristic_modify от ability_level самого агрегата.
   */
  aggregate?: {
    characteristic_code: string;
    method_keyword: string;
    /** levels[N-1] = минимум методов со стоимостью ≥ N для уровня N. */
    levels: number[];
  } | null;
  /**
   * Производный уровень способности (D109): уровень = число порогов thresholds, не
   * превышающих «опыт» = сумму стоимостей взятых способностей с признаком source_keyword
   * (напр. «Ближний бой»: опыт ближнего боя → уровень при 2/8/16).
   */
  derived_level?: { source_keyword: string; thresholds: number[] } | null;
}
