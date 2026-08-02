import type { AbilitySpecBase } from './AbilitySpecBase'
import type { ActionCost } from './ActionCost'
import type { ProcessSpec } from './ProcessSpec'
import type { SpellSpec } from './SpellSpec'

/** Чистый слой — дискриминированный юнион, выдаётся на границе (эмит). */
export type AbilitySpec =
  | (AbilitySpecBase & { type: 'trait' | 'feature' | 'skill' })
  | (AbilitySpecBase & { type: 'action'; action_costs: ActionCost[] })
  | (AbilitySpecBase & { type: 'process'; process: ProcessSpec })
  | (AbilitySpecBase & { type: 'spell'; action_costs: ActionCost[]; spell: SpellSpec })
