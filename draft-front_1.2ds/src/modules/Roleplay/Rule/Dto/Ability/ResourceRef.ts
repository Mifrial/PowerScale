import type { CharacteristicRef } from './CharacteristicRef'

export interface ResourceRef extends CharacteristicRef {
  isDimensional?: boolean
}
