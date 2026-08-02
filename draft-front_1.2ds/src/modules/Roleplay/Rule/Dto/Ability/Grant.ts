import type { DimensionalNumberValue } from '@/modules/Core/Engine/Value/DimensionalNumber'
import type { Formula } from './Formula'

export type Grant =
  | { type: 'characteristic'; characteristic_code: string; value: DimensionalNumberValue; permanent?: boolean }
  | { type: 'characteristic_modify'; characteristic_code: string; amount: Formula; source_code: string; permanent?: boolean }
  | { type: 'resource'; resource_code: string; limit: DimensionalNumberValue | number; permanent?: boolean }
  | { type: 'resource_limit_change'; resource_code: string; amount: Formula; source_code: string; permanent?: boolean }
  | { type: 'ability'; ability_code: string; permanent?: boolean }
  | { type: 'keyword'; keyword_code: string; remove?: boolean; permanent?: boolean }
  | { type: 'item'; item_code: string; permanent?: boolean }
