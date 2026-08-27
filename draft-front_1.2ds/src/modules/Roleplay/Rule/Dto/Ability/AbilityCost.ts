export type AbilityCost =
  | { kind: 'array'; levels_cost: number[] }
  | { kind: 'progression'; max_level: number; base_cost: number; step: number }
  | { kind: 'automatic' }
  /**
   * Параметрическая цена: стоимость уровня = per_unit × значение параметра parameter_code
   * (выбор игрока при покупке; дефолт из AbilityParameter.default). Напр. «Сопротивление магии x»: 2×x.
   */
  | { kind: 'parameter'; parameter_code: string; per_unit: number }
  /**
   * Табличная цена параметра «X»: стоимость = costs[значение параметра] (нелинейная, с отрицательными
   * значениями; напр. «Врождённая Сила X»: −3→−3, −2→−2, −1→−1, +1→2, +2→4, +3→8 ОС).
   * Ключи — десятичные строки значений параметра (в т.ч. отрицательные).
   */
  | { kind: 'parameter_table'; parameter_code: string; costs: Record<string, number> }
  /**
   * Сумма табличных цен нескольких параметров покупки (напр. «Физическое развитие»:
   * Сила/Стойкость/Ловкость). Итоговая стоимость = сумма costs[значение] по каждому коду.
   * max_level — потолок суммы параметров (уровень способности для требований).
   */
  | {
      kind: 'parameter_sum_tables';
      max_level: number;
      tables: Record<string, Record<string, number>>;
    };
