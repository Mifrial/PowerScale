import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneDetail } from '@/modules/Roleplay/Scene/Dto/SceneDetail';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneEnclosure';
import type { SaveSceneData } from '@/modules/Roleplay/Scene/Dto/SaveSceneData';
import type { SceneFindPageQuery } from '@/modules/Roleplay/Scene/Dto/SceneFindPageQuery';
import type { ScenePage } from '@/modules/Roleplay/Scene/Dto/ScenePage';
import type { ISceneApi } from '@/modules/Roleplay/Scene/Interface/ISceneApi';
import { mockArchiveScene } from '@/modules/Roleplay/Scene/Mock/mockArchiveScene';
import { mockFindScenePage } from '@/modules/Roleplay/Scene/Mock/mockFindScenePage';
import { mockGetScene } from '@/modules/Roleplay/Scene/Mock/mockGetScene';
import { mockSaveScene } from '@/modules/Roleplay/Scene/Mock/mockSaveScene';

let nextSceneId = 100;

/** Mock-транспорт локаций. */
export const mockSceneApi: ISceneApi = {
  async findPage(query: SceneFindPageQuery): Promise<ScenePage> {
    const page = mockFindScenePage();
    const filtered = page.items.filter((scene) => {
      if (Boolean(scene.isArchived) !== Boolean(query.isArchived)) return false;
      if (query.q && !scene.name.toLowerCase().includes(query.q.toLowerCase())) return false;

      return true;
    });

    return { items: filtered.slice(query.offset, query.offset + query.limit), total: filtered.length };
  },

  async get(id: number): Promise<SceneDetail> {
    return mockGetScene(id);
  },

  async save(data: SaveSceneData): Promise<SceneDetail> {
    const existing = data.id === undefined ? null : mockGetScene(data.id);
    const now = Math.floor(Date.now() / 1000);
    const boardIdMap = new Map<number, number>();
    const boards: SceneBoard[] = data.scene.boards.map((board, index) => {
      const id = board.id > 0 ? board.id : 1000 + index;
      boardIdMap.set(board.id, id);

      return {
        id,
        name: board.name,
        sortOrder: board.sortOrder,
        baseFile: board.filePublicId === null ? null : { publicId: board.filePublicId, width: null, height: null },
        boundWidth: board.boundWidth,
        boundHeight: board.boundHeight,
        occupancyCell: board.occupancyCell,
        zQuantum: board.zQuantum,
        gridStep: board.gridStep,
      };
    });
    const elements: SceneElement[] = data.scene.elements.map((element, index) => ({
      id: element.id > 0 ? element.id : 2000 + index,
      boardId: boardIdMap.get(element.boardId) ?? element.boardId,
      kind: element.kind,
      shape: element.shape,
      props: element.props,
      sortOrder: element.sortOrder,
      targetBoardId: element.targetBoardId === null ? null : (boardIdMap.get(element.targetBoardId) ?? null),
      asset:
        typeof element.props.assetPublicId === 'string' && element.props.assetPublicId !== ''
          ? { publicId: element.props.assetPublicId, contentBox: null }
          : null,
    }));
    const enclosures: SceneEnclosure[] = data.scene.enclosures.map((enclosure, index) => ({
      id: enclosure.id > 0 ? enclosure.id : 3000 + index,
      parentBoardId: boardIdMap.get(enclosure.parentBoardId) ?? enclosure.parentBoardId,
      childBoardId: boardIdMap.get(enclosure.childBoardId) ?? enclosure.childBoardId,
      footprint: enclosure.footprint,
      sortOrder: enclosure.sortOrder,
    }));
    const detail: SceneDetail = {
      id: existing?.id ?? nextSceneId++,
      ownerId: existing?.ownerId ?? 1,
      name: data.scene.name,
      description: data.scene.description,
      resolution: data.scene.resolution,
      schemaVersion: data.scene.schemaVersion,
      isArchived: existing?.isArchived ?? false,
      version: (existing?.version ?? 0) + 1,
      updatedBy: 1,
      createdAt: existing?.createdAt ?? now,
      updatedAt: now,
      canEdit: true,
      boards,
      elements,
      enclosures,
    };

    return mockSaveScene(detail);
  },

  async archive(id: number): Promise<void> {
    mockArchiveScene(id);
  },
};
