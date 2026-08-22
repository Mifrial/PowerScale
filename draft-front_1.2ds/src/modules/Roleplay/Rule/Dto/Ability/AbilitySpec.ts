import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import type { ActionComponent } from '@/modules/Roleplay/Rule/Dto/Ability/ActionComponent';
import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import type { SpellSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SpellSpec';
import type { GroupSpec } from '@/modules/Roleplay/Rule/Dto/Ability/GroupSpec';
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';

/** Чистый слой — дискриминированный юнион, выдаётся на границе (эмит). */
export type AbilitySpec =
  | GroupSpec
  | (AbilitySpecBase & { type: Exclude<AbilityType, 'action' | 'process' | 'spell' | 'group'> })
  | (AbilitySpecBase & { type: 'action'; action_components: ActionComponent[] })
  | (AbilitySpecBase & { type: 'process'; process: ProcessSpec })
  | (AbilitySpecBase & { type: 'spell'; action_components: ActionComponent[]; spell: SpellSpec });
