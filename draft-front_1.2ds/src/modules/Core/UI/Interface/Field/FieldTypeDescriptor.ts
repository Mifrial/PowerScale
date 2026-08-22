import type { Component } from 'vue';
import type { IFieldTypeInterpreter } from '@/modules/Core/UI/Interface/Field/IFieldTypeInterpreter';

export interface FieldTypeDescriptor {
  interpreter: IFieldTypeInterpreter;
  cell?: Component;
  filterWidget?: Component;
}
