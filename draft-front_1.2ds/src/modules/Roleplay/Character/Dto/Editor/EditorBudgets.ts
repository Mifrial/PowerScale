import type { EditorBudget } from '@/modules/Roleplay/Character/Dto/Editor/EditorBudget';

/** Бюджеты редактора: ОС/ОЛ/ОР и деньги. */
export interface EditorBudgets {
  os: EditorBudget;
  ol: EditorBudget;
  or: EditorBudget;
  money: EditorBudget;
  /**
   * Доплата механики «Общие черты» (purchase_surcharge): за 3-ю и последующие общие черты.
   * Отсутствует, когда доплаты нет (взято ≤ free_count черт фильтра).
   */
  osSurcharge?: {
    /** Суммарная доплата в ОС (уже включена в os.spent). */
    total: number;
    /** Доплаченные способности: код правила + размер доплаты. */
    items: { abilityCode: string; amount: number }[];
  };
}
