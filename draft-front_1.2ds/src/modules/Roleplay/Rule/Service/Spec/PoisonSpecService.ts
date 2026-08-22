import type { PoisonSpec } from '@/modules/Roleplay/Rule/Dto/Poison/PoisonSpec';

export class PoisonSpecService {
  createEmpty(): PoisonSpec {
    return {
      damage_type_code: '',
    };
  }
}
