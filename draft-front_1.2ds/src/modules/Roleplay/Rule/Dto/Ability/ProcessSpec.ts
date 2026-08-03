import type { ProcessStep } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessStep'
import type { ProcessTransition } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessTransition'

export interface ProcessSpec {
  steps: ProcessStep[]
  start_step_code?: string
  transition: ProcessTransition
  failure?: 'restart_from_first' | 'end_action' | null
}
