import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult'

export interface ChatMessage {
  id: number
  chatId: number
  userId: number
  username: string
  content: string
  rolls: DiceRollResult[]
  createdAt: string
  updatedAt: string
}
