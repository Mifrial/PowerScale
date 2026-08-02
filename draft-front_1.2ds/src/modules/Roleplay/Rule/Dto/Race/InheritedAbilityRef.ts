import type { RaceAbilityRef } from './RaceAbilityRef'

export interface InheritedAbilityRef extends RaceAbilityRef {
  /** Название вида/подвида, из которого способность наследуется. */
  fromName: string
}
