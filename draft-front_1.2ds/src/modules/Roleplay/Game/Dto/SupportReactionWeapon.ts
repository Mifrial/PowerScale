import type { WeaponProfileType } from '@/modules/Roleplay/Rule/Enum/Item/WeaponProfileType';

/** Надетое оружие, которым можно поддержать удар. */
export interface SupportReactionWeapon {
  itemInventoryId: number;
  itemRuleCode: string;
  name: string;
  profileType: WeaponProfileType;
}
