import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { SenseStatus } from '@/modules/Roleplay/Rule/Enum/SenseStatus';

/**
 * Спека чувства (type='sense'): базовое состояние чувства персонажа.
 * Модификаторы добавляются через гранты `sense_modify` и хранятся в экземпляре персонажа.
 */
export interface SenseSpec {
  type: 'sense';
  status: SenseStatus;
  radius: DimensionalNumberValue;
}
