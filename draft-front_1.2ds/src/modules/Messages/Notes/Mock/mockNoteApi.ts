import type { INoteApi } from '@/modules/Messages/Notes/Interface/INoteApi';
import * as mock from '@/modules/Messages/Notes/Mock/mockNotes';

export const mockNoteApi: INoteApi = {
  findPage: mock.mockFindPage,
  findHosts: mock.mockFindHosts,
  get: mock.mockGet,
  create: mock.mockCreate,
  update: mock.mockUpdate,
  delete: mock.mockDelete,
  reorder: mock.mockReorder,
  pin: mock.mockPin,
};
