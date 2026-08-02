import type { Engine } from '@/modules/Core/Engine/Service/Engine'
import type { IMacroApi, CreateMacroData, UpdateMacroData } from '@/modules/Roleplay/Game/Interface/IMacroApi'
import type { UserMacro } from '@/modules/Roleplay/Game/Dto/UserMacro'

export class MacroApi implements IMacroApi {
  constructor(private engine: Engine) {}

  async getMyMacros(signal?: AbortSignal): Promise<UserMacro[]> {
    const res = await this.engine.runAction<UserMacro[]>('user.macro.getList', undefined, signal)
    return res.data ?? []
  }

  async createMacro(data: CreateMacroData, signal?: AbortSignal): Promise<UserMacro> {
    const res = await this.engine.runAction<UserMacro>('user.macro.create', data, signal)
    if (!res.data) throw new Error('Failed to create macro')
    return res.data
  }

  async updateMacro(id: number, data: UpdateMacroData, signal?: AbortSignal): Promise<UserMacro> {
    const res = await this.engine.runAction<UserMacro>('user.macro.update', { id, ...data }, signal)
    if (!res.data) throw new Error('Failed to update macro')
    return res.data
  }

  async deleteMacro(id: number, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction('user.macro.delete', { id }, signal)
  }
}
