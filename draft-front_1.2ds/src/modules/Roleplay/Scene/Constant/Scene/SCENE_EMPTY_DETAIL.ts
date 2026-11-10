import type { SceneDetail } from '@/modules/Roleplay/Scene/Dto/SceneDetail';

/** Пустой снимок до загрузки локации в редактор. */
export const SCENE_EMPTY_DETAIL: SceneDetail = {
  id: 0,
  ownerId: 0,
  name: '',
  description: '',
  resolution: 64,
  schemaVersion: 1,
  isArchived: false,
  version: 0,
  updatedBy: null,
  createdAt: 0,
  updatedAt: 0,
  canEdit: false,
  boards: [],
  elements: [],
  enclosures: [],
};
