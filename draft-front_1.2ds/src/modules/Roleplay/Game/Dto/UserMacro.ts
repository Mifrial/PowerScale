import type { MacroRollSpec } from '@/modules/Roleplay/Game/Dto/MacroRollSpec'

/** Шаблон сообщения игрока (ТР §3 user_macros). Макрос = преднастроенное сообщение:
 * текст и/или список бросков; валиден при непустом name и (textTemplate ИЛИ rolls.length > 0). */
export interface UserMacro {
  id: number
  userId: number
  name: string
  textTemplate: string
  rolls: MacroRollSpec[]
  createdAt: string
}
