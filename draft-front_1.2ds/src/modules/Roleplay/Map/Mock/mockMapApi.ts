import type { IMapApi } from '@/modules/Roleplay/Map/Interface/IMapApi';
import * as mock from '@/modules/Roleplay/Map/Mock/mockMaps';

export const mockMapApi: IMapApi = {
  findPage: mock.mockFindMapPage,
  get: mock.mockGetMap,
  save: mock.mockSaveMap,
  archive: mock.mockArchiveMap,
  route: mock.mockRouteMap,
};
