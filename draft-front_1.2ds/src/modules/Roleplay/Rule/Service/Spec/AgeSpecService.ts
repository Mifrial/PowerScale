import type { AgeSpec } from '@/modules/Roleplay/Rule/Dto/Age/AgeSpec';
import type { Age } from '@/modules/Roleplay/Rule/Dto/Age/Age';
import type { AgeEffect } from '@/modules/Roleplay/Rule/Dto/Age/AgeEffect';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';

export class AgeSpecService {
  createEmpty(): AgeSpec {
    return { type: 'age', ages: [this.createEmptyStage()] };
  }

  fromRuleSpec(value: RuleSpec | null): AgeSpec {
    if (value && typeof value === 'object' && 'type' in value && value.type === 'age') {
      const loaded = structuredClone(value);

      return {
        type: 'age',
        ages: loaded.ages?.length ? loaded.ages : [this.createEmptyStage()],
      };
    }

    return this.createEmpty();
  }

  createEmptyStage(): Age {
    return { name: '', ol: 0, featureLimit: 0, effects: [] };
  }

  createEmptyEffect(): AgeEffect {
    return { characteristic_code: '', delta: 0 };
  }
}
