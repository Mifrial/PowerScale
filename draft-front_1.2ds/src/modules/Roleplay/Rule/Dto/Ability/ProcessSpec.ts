import type { ProcessStep } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessStep';
import type { ProcessTransition } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessTransition';
import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';
import type { ProcessCounter } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessCounter';

export interface ProcessSpec {
  steps: ProcessStep[];
  start_step_code?: string;
  exit_step_codes?: string[];
  transition: ProcessTransition;
  failure?: 'restart_from_first' | 'end_action' | null;
  completion_effects?: ActionEffect[];
  /** Счётчики сессии; нет ключа — процесса без счётчиков. */
  counters?: ProcessCounter[];
  /** Режим цели; нет ключа — any. */
  target_mode?: 'any' | 'single';
  /** Помеха обстоятельств = число прошлых ударов этим экземпляром оружия в сессии. */
  repeat_weapon_circumstance?: boolean;
}
