import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec';

export class ResourceSpecService {
  createEmpty(): ResourceSpec {
    return {
      is_dimensional: true,
      auto_add: false,
      check_token: false,
      limit: { base: { base: 3, size: 0 }, adjustments: [] },
    };
  }
}
