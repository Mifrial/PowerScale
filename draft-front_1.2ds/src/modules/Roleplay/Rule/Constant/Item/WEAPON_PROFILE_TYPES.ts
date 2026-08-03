import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';

export const WEAPON_PROFILE_TYPES: { label: string; value: WeaponProfile['type'] }[] = [
  { label: 'Удар', value: 'strike' },
  { label: 'Бросок', value: 'throw' },
  { label: 'Выстрел', value: 'shoot' },
];
