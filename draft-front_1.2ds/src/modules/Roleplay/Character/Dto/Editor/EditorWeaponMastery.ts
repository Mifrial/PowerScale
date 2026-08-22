import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Мастерство конкретного оружия семьи (владение): значение = стат мастерства + бонус владения. */
export interface EditorWeaponMastery {
  weaponName: string;
  value: DimensionalNumberValue;
  valueLabel: string;
  /** Бонус владения (уровень «Владения оружием» семьи). */
  bonus: number;
}
