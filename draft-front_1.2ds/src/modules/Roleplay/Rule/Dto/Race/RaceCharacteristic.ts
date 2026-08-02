import type { DimensionalNumberValue } from '@/modules/Core/Engine/Value/DimensionalNumber'
import type { RaceCharacteristicMode } from '@/modules/Roleplay/Rule/Enum/Race/RaceCharacteristicMode'
import type { RacePurchaseLevel } from './RacePurchaseLevel'

export interface RaceCharacteristic {
  characteristic_code: string
  mode: RaceCharacteristicMode
  /** fixed — фикс. база; purchased — минимум (значение за 0 ОС). */
  base: DimensionalNumberValue
  /** Только при mode='purchased': лестница закупки. */
  purchase?: RacePurchaseLevel[]
}
