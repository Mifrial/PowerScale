import type { CharacteristicGroup } from '@/modules/Roleplay/Rule/Enum/CharacteristicGroup';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/**
 * Спека характеристики (type='characteristic'): формула производных и категория карточки.
 * Данные как есть: редактор эмитит `{ type: 'characteristic', formula: null }`
 * для непроизводной характеристики; `type` внутри спеки дублирует Rule.type (исторически).
 * `group` отсутствует у правил, созданных редактором (фолбэк `primary` при отрисовке карточки).
 */
export interface CharacteristicSpec {
  type: 'characteristic';
  formula?: string | null;
  group?: CharacteristicGroup;
  /**
   * Автоматическое получение: характеристика есть у всех персонажей. База по умолчанию 3 средних
   * ({3|0}); для нестандартной базы (напр. «Мастерство боя» — 3 маленьких {3|-1}) передаётся
   * значение. Раса (fixed-база) или дар характеристики могут переопределить базу — берётся их значение.
   */
  automatic?: boolean | { value: DimensionalNumberValue };
  /**
   * База характеристики берётся из другой характеристики + её модификаторов с указанными
   * источниками. Пример — «Вес»: base_from { characteristic_code: 'strength', source_codes: ['innate'] }
   * (база = база Силы + врождённые модификаторы Силы). Производные значения (по формуле) не участвуют.
   */
  base_from?: { characteristic_code: string; source_codes: string[] };
  /** Профили оружия, чьё мастерство показывает эта характеристика. Нет поля — блока оружий нет. */
  weapon_mastery?: { profiles: ('strike' | 'throw' | 'shoot')[] };
  /** Единица перевода повреждений в истощение. Нет флага — запас { base: 1, size: 0 }. */
  damage_endurance?: boolean;
  /** Характеристика воли. Нет флага — проверка истощения берёт { base: 1, size: 0 }. */
  willpower?: boolean;
  /** Учитывается в пороге концентрации. Нет флага — порог по этой характеристике не проверяется. */
  concentration_threshold?: boolean;
  /** На проверку по этой характеристике можно потратить жетон концентрации. Нет флага — нельзя. */
  concentration_token?: boolean;
  /** Пул и помеха броска при неустойчивости. Нет флага — бросок не подставляет эту характеристику. */
  unstable_roll?: boolean;
  /** Запасная характеристика инициативы. Нет флага — диалог не подставляет код. */
  initiative?: boolean;
  /** Поглощение уклонения читает эту характеристику. Нет флага — характеристика не читается. */
  dodge_soak?: boolean;
}
