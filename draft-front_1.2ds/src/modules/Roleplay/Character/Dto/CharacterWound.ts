/** Sidecar записи состояния «Рана»: вклады закрытости и зажим. */
export interface CharacterWound {
  bandage: number;
  clotting: number;
  internal: boolean;
  aided: boolean;
  heldBy: string | null;
}
