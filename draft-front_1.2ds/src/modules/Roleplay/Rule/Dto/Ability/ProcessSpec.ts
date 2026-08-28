import type { ProcessStep } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessStep';
import type { ProcessTransition } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessTransition';
import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';

export interface ProcessSpec {
  steps: ProcessStep[];
  start_step_code?: string;
  exit_step_codes?: string[];
  transition: ProcessTransition;
  failure?: 'restart_from_first' | 'end_action' | null;
  completion_effects?: ActionEffect[];
}
