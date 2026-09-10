import { registerMenuItem } from '@/modules/Core/UI/init';
import { HOME_MENU_ITEM } from '@/modules/Roleplay/Home/Constant/Navigation/HOME_MENU_ITEM';

export function registerHomeModule(): void {
  registerMenuItem(HOME_MENU_ITEM);
}
