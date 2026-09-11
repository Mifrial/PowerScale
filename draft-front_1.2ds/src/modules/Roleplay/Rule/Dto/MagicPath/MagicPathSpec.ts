import type { MagicPathStudyCost } from '@/modules/Roleplay/Rule/Dto/MagicPath/MagicPathStudyCost';

/** Спека правила type='magic_path': проверка сотворения и правила цены изучения. */
export interface MagicPathSpec {
  type: 'magic_path';
  /** Проверка характеристики пути (напр. check-willpower); бросок каста — check-spell-cast. */
  check_code: string | null;
  study_cost: MagicPathStudyCost | null;
  /**
   * Пути, чьё волшебство этот путь считает своим (односторонне).
   * Шаман включает псионика: навыки/заклинания псионика доступны шаману без повторной покупки.
   */
  includes_path_codes: string[];
}
