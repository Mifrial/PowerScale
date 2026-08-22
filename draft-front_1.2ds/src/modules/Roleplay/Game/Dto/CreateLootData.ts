/**
 * Создание/редактирование записи добычи ведущим (запас, prepared). Ровно один из
 * `itemRuleId` (предмет) или `moneyAmount` (деньги) должен быть задан.
 */
export interface CreateLootData {
  group: string | null;
  itemRuleId: string | null;
  quantity: number;
  moneyAmount: number | null;
  notes: string | null;
}
