import type { CharacterWoundHolder } from '@/modules/Roleplay/Character/Dto/CharacterWoundHolder';

/** Sidecar записи состояния «Рана»: вклады закрытости и зажим. */
export interface CharacterWound {
  bandage: number;
  clotting: number;
  internal: boolean;
  aided: boolean;
  heldBy: string | CharacterWoundHolder | null;
}
