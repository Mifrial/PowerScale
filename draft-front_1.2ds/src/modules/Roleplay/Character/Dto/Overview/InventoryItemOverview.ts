export interface InventoryItemOverview {
  id: number;
  /** null — кастомный «предмет мастера» (без правила). */
  ruleCode: string | null;
  name: string;
  categoryLabel: string;
  quantity: number;
  equipped: boolean;
  durabilityLeft: number | null;
  note: string | null;
  href: string | null;
  isResolved: boolean;
  /** Имена применённых модификаторов предмета. */
  modifierNames: string[];
}
