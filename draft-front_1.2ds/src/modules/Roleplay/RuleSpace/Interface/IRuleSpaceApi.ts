import type { Space } from '@/modules/Roleplay/Space/Dto/Space';
import type { SpaceCreateData } from '@/modules/Roleplay/Space/Dto/SpaceCreateData';
import type { SpaceUpdateData } from '@/modules/Roleplay/Space/Dto/SpaceUpdateData';
import type { SpaceRevisionMeta } from '@/modules/Roleplay/Space/Dto/SpaceRevisionMeta';
import type { SpaceRevision } from '@/modules/Roleplay/Space/Dto/SpaceRevision';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

export interface ISpaceApi {
  getSpaces(signal?: AbortSignal): Promise<Space[]>;
  getSpace(id: number, signal?: AbortSignal): Promise<Space>;
  getSpaceByCode(code: string, signal?: AbortSignal): Promise<Space>;
  createSpace(data: SpaceCreateData, signal?: AbortSignal): Promise<Space>;
  updateSpace(id: number, data: SpaceUpdateData, signal?: AbortSignal): Promise<Space>;
  deactivateSpace(id: number, signal?: AbortSignal): Promise<void>;

  getRevisions(spaceId: number, signal?: AbortSignal): Promise<SpaceRevisionMeta[]>;
  getRevision(spaceId: number, revision: number, signal?: AbortSignal): Promise<SpaceRevision<Rule>>;
  commitDraft(
    spaceId: number,
    rules: Rule[],
    signal?: AbortSignal,
    removedCodes?: string[],
  ): Promise<SpaceRevision<Rule>>;
}
