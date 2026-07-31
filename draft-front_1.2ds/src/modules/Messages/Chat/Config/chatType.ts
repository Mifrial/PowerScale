import type { ChatType } from '../Interface/types'

export const CHAT_CONFIG: Record<ChatType, { icon: string; color: string }> = {
  private: { icon: 'mdi-account', color: 'primary' },
  group: { icon: 'mdi-account-group', color: 'success' },
  game: { icon: 'mdi-dice-d6', color: 'warning' },
  game_discussion: { icon: 'mdi-forum', color: 'info' },
  character_discussion: { icon: 'mdi-account-details', color: 'accent' },
}

export function chatIcon(type: ChatType): string {
  return CHAT_CONFIG[type]?.icon ?? 'mdi-chat'
}

export function chatColor(type: ChatType): string {
  return CHAT_CONFIG[type]?.color ?? 'grey'
}
