import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';

export class StateSpecService {
  createEmpty(): StateSpec {
    return {
      value_type: 'flag',
      aggregation: 'sum',
      effects: [],
    };
  }
}
