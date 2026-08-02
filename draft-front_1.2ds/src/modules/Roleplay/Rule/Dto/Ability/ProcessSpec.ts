import type { ProcessStep } from './ProcessStep'
import type { ProcessTransition } from './ProcessTransition'

export interface ProcessSpec {
  steps: ProcessStep[]
  start_step_code?: string
  transition: ProcessTransition
  failure?: 'restart_from_first' | 'end_action' | null
}
