import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec';

export class ResourceSpecService {
  createEmpty(): ResourceSpec {
    return {
      is_dimensional: true,
      initial_value: null,
    };
  }
}

export const resourceSpecService = new ResourceSpecService();
