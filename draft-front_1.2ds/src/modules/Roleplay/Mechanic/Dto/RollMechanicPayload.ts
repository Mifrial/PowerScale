export interface RollMechanicPayload {
  diceCount?: number;
  dieFaces?: number;
  efficiency?: number;
  adv?: number;
  dieSize?: number;
  /** Коды механик, которые механика броска применяет всегда (если их правила есть в ревизии). */
  sub_mechanics?: string[];
}
