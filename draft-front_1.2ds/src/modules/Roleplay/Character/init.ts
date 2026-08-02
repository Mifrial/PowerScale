import { registerPermissionCategory } from '@/modules/Core/User/init'
import { CHARACTER_PERMISSION_CATEGORY } from '@/modules/Roleplay/Character/Constant/permissions'

export function registerCharacterModule(): void {
  registerPermissionCategory(CHARACTER_PERMISSION_CATEGORY)
}
