import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator'
import type { IMacroApi } from '@/modules/Roleplay/Game/Interface/IMacroApi'
import { rollService } from '@/modules/Roleplay/Game/Service/RollService'
import { registerCommandHandler, registerContentRenderer, registerToolbarExtension } from '@/modules/Messages/Chat/init'
import { registerProfileSection, registerPermissionCategory } from '@/modules/Core/User/init'
import { GAME_PERMISSION_CATEGORY } from '@/modules/Roleplay/Game/Constant/permissions'
import DiceRollResult from '@/modules/Roleplay/Game/Component/DiceRollResult.vue'
import RollFormExtension from '@/modules/Roleplay/Game/Component/RollFormExtension.vue'
import MacroBarExtension from '@/modules/Roleplay/Game/Component/MacroBarExtension.vue'
import MacrosSection from '@/modules/Roleplay/Game/Component/MacrosSection.vue'

export function registerMacroApi(api: IMacroApi): void {
  serviceLocator.set('Roleplay.Game.Service.MacroApi', api)
}

export function getMacroApi(): IMacroApi {
  return serviceLocator.get('Roleplay.Game.Service.MacroApi')
}

export function registerGameModule(): void {
  registerPermissionCategory(GAME_PERMISSION_CATEGORY)
  registerCommandHandler({ command: 'roll', parse: input => rollService.parseRollCommand(input) })
  registerContentRenderer({ type: 'roll', component: DiceRollResult })
  registerToolbarExtension({ id: 'roll-form', component: RollFormExtension })
  registerToolbarExtension({ id: 'macro-bar', component: MacroBarExtension })
  registerProfileSection({ id: 'macros', component: MacrosSection })
}
