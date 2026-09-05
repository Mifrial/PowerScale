import type { IChatApi } from '@/modules/Messages/Chat/Interface/IChatApi';
import * as mock from '@/modules/Messages/Chat/Mock/mockChat';

export const mockChatApi: IChatApi = {
  getChats: mock.mockGetChats,
  findMessagePage: mock.mockFindMessagePage,
  getMessages: mock.mockGetMessages,
  getMessagesBefore: mock.mockGetMessagesBefore,
  getTotalMessageCount: mock.mockGetTotalMessageCount,
  sendMessage: mock.mockSendMessage,
  updateMessageVisibility: mock.mockUpdateMessageVisibility,
  sendSystemMessage: mock.mockSendSystemMessage,
  markChatRead: mock.mockMarkChatRead,
  addPrivate: mock.mockAddPrivate,
  addGroup: mock.mockAddGroup,
  sync: mock.mockSync,
};
