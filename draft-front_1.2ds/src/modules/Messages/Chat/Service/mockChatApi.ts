import type { IChatApi } from '../Interface/IChatApi'
import * as mock from './mockChat'

export const mockChatApi: IChatApi = {
  getChats: mock.mockGetChats,
  getMessages: mock.mockGetMessages,
  getTotalMessageCount: mock.mockGetTotalMessageCount,
  sendMessage: mock.mockSendMessage,
  markChatRead: mock.mockMarkChatRead,
  sync: mock.mockSync,
}
