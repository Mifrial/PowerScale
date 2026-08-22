import type { FieldTypeDescriptor } from '@/modules/Core/UI/Interface/Field/FieldTypeDescriptor';

export class FieldTypeRegistry {
  private readonly descriptors = new Map<string, FieldTypeDescriptor>();

  register(type: string, descriptor: FieldTypeDescriptor): void {
    this.descriptors.set(type, descriptor);
  }

  get(type: string): FieldTypeDescriptor | undefined {
    return this.descriptors.get(type);
  }
}
