import type { WeaponProfileType } from '@/modules/Roleplay/Rule/Enum/Item/WeaponProfileType';

/** Надетое оружие, которым можно объявить реакцию. */
export interface ReactionWeapon {
  itemInventoryId: number;
  itemRuleCode: string;
  name: string;
  profileType: WeaponProfileType;
}
