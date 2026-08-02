import type { IMacroApi } from '@/modules/Roleplay/Game/Interface/IMacroApi'
import * as mock from '@/modules/Roleplay/Game/Mock/mockMacros'

export const mockMacroApi: IMacroApi = {
  getMyMacros: mock.mockGetMyMacros,
  createMacro: mock.mockCreateMacro,
  updateMacro: mock.mockUpdateMacro,
  deleteMacro: mock.mockDeleteMacro,
}
