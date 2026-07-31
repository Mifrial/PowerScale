import { sl } from '@/modules/Core/Engine/ServiceLocator'
import type { IChatApi } from '@/modules/Messages/Chat/Interface/IChatApi'

export function registerChatApi(api: IChatApi): void {
  sl.set('Messages.Chat.Service.ChatApi', api)
}

export function getChatApi(): IChatApi {
  return sl.get('Messages.Chat.Service.ChatApi')
}
