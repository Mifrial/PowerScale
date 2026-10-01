/**
 * Честный разбор mechanicPayload: JSON как есть, без сужения до union и без подмены пустого массива на null.
 */
export class MechanicPayloadInspectorService {
  private readonly knownTypes: Readonly<Record<string, true>>;

  constructor(knownTypes: Readonly<Record<string, true>>) {
    this.knownTypes = knownTypes;
  }

  isAbsent(payload: unknown): boolean {
    if (payload == null) return true;

    return Array.isArray(payload) && payload.length === 0;
  }

  isKnownType(payload: unknown): boolean {
    if (payload === null || typeof payload !== 'object' || Array.isArray(payload)) return false;
    const type = 'type' in payload ? payload.type : undefined;
    if (typeof type !== 'string') return false;

    return this.knownTypes[type] === true;
  }

  format(payload: unknown): string {
    return JSON.stringify(payload, null, 2);
  }

  parse(text: string): Record<string, unknown> | unknown[] {
    const trimmed = text.trim();
    if (trimmed === '') {
      throw new Error('Пустой payload механики');
    }
    let parsed: unknown;
    try {
      parsed = JSON.parse(trimmed);
    } catch {
      throw new Error('Некорректный JSON payload механики');
    }
    if (parsed === null || typeof parsed !== 'object') {
      throw new Error('Payload механики должен быть объектом или массивом');
    }
    if (Array.isArray(parsed)) return parsed;

    return parsed as Record<string, unknown>;
  }
}
