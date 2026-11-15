import type { MagicPathStudyCost } from '@/modules/Roleplay/Rule/Dto/MagicPath/MagicPathStudyCost';

/** Спека правила type='magic_path': проверка сотворения и правила цены изучения. */
export interface MagicPathSpec {
  type: 'magic_path';
  /** Проверка характеристики пути (напр. check-willpower). */
  check_code: string | null;
  /** Проверка сотворения, которую называет путь (напр. check-spell-cast). */
  cast_check_code: string | null;
  /** Характеристика мощи сотворения (напр. magic-power). */
  power_characteristic_code: string | null;
  /** Характеристика контроля сотворения (напр. magic-control). */
  control_characteristic_code: string | null;
  study_cost: MagicPathStudyCost | null;
  /**
   * Пути, чьё волшебство этот путь считает своим (односторонне).
   * Шаман включает псионика: навыки/заклинания псионика доступны шаману без повторной покупки.
   */
  includes_path_codes: string[];
  /**
   * Путь не использует Контроль магии: Сложность сотворения считает только
   * мощь. «Простейшее волшебство» кристалла.
   */
  no_control?: boolean;
  /**
   * Заклинания пути берутся из записей источника (`payload.recorded_spells`),
   * а не из изученных экземпляров. Только такой путь кастует из кристалла.
   */
  recorded_casting?: boolean;
}
