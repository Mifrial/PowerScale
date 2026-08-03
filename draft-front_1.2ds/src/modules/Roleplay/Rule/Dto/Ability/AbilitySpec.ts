import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import type { ActionCost } from '@/modules/Roleplay/Rule/Dto/Ability/ActionCost';
import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import type { SpellSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SpellSpec';
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';

/** Чистый слой — дискриминированный юнион, выдаётся на границе (эмит). */
export type AbilitySpec =
  | (AbilitySpecBase & { type: Exclude<AbilityType, 'action' | 'process' | 'spell'> })
  | (AbilitySpecBase & { type: 'action'; action_costs: ActionCost[] })
  | (AbilitySpecBase & { type: 'process'; process: ProcessSpec })
  | (AbilitySpecBase & { type: 'spell'; action_costs: ActionCost[]; spell: SpellSpec });
