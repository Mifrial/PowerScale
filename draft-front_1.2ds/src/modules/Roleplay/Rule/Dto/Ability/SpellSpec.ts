import type { DimensionalNumberValue } from '@/modules/Core/Engine/Value/DimensionalNumber'
import type { SpellDuration } from './SpellDuration'
import type { SpellComponent } from './SpellComponent'

export interface SpellSpec {
  difficulty: DimensionalNumberValue
  duration: SpellDuration
  components: SpellComponent[]
}
