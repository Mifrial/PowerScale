export type ChatType = 'private' | 'group' | 'game' | 'game_discussion' | 'character_discussion'

export type ChatVisibility = 'public' | 'members' | 'invite'

export interface MemberInfo {
  userId: number
  status: string
  joinedAt: string
}

export interface Chat {
  id: number
  type: ChatType
  name: string
  unreadCount: number
  lastMessage?: string
  lastMessageAt: string
  members: MemberInfo[]
  visibility?: ChatVisibility
}

export interface DiceRollSpec {
  diceCount: number
  dieSize: number
  dieFaces: number
  efficiency: number
  adv: number
  modifier: number
  label?: string
}

export interface DiceRollResult {
  spec: DiceRollSpec
  rolls: number[]
  successes: number[]
  adjustedRolls: number[]
  totalSuccesses: number
}

export interface SyncResponse {
  now: string
  chats: Chat[]
  newChats: Chat[]
  messages: Record<number, ChatMessage[]>
}

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
