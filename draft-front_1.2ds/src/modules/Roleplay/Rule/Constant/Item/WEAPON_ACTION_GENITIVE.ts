import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';

/** Родительный падеж действия для формул силы: «Сила удара», «Сила броска», «Сила выстрела». */
export const WEAPON_ACTION_GENITIVE: Record<WeaponProfile['type'], string> = {
  strike: 'удара',
  throw: 'броска',
  shoot: 'выстрела',
};
