import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { MaybeFilterValue } from '@/modules/Core/UI/Dto/Filter/MaybeFilterValue';
import type { IFieldTypeInterpreter } from '@/modules/Core/UI/Interface/Field/IFieldTypeInterpreter';
import { formatDatetime } from '@/modules/Core/UI/Utils/formatDatetime';

export class BaseFieldTypeInterpreter implements IFieldTypeInterpreter {
  isActive(field: FilterField, value: MaybeFilterValue): boolean {
    if (field.type === 'boolean') return value === true;
    if (value === undefined || value === null) return false;
    if (typeof value === 'string') return value !== '';
    if (typeof value === 'object') {
      if (value.mode === 'equals' || value.mode === 'contains') return !!value.value;
      if (value.mode === 'from') return value.from !== undefined && value.from !== null;
      if (value.mode === 'to') return value.to !== undefined && value.to !== null;
      if (value.mode === 'interval') return value.from !== undefined || value.to !== undefined;

      return true;
    }

    return true;
  }

  predicate(field: FilterField, value: MaybeFilterValue): (rowValue: unknown) => boolean {
    if (field.type === 'number') return this.numberPredicate(value);
    if (field.type === 'datetime' || field.type === 'date') return this.dateTimePredicate(value);
    if (field.type === 'boolean' || field.type === 'select' || field.type === 'active') {
      return (rowValue) => rowValue === value;
    }

    return this.stringPredicate(value);
  }

  compare(field: FilterField, a: unknown, b: unknown): number {
    if (a === null || a === undefined) return 1;
    if (b === null || b === undefined) return -1;
    if (field.type === 'number') return Number(a) - Number(b);
    if (field.type === 'datetime' || field.type === 'date') {
      const ta = toTime(a);
      const tb = toTime(b);
      if (ta === null && tb === null) return 0;
      if (ta === null) return 1;
      if (tb === null) return -1;

      return ta - tb;
    }
    if (field.type === 'boolean' || field.type === 'active' || field.type === 'select') {
      return compareScalar(a, b);
    }

    return String(a).localeCompare(String(b));
  }

  format(field: FilterField, value: MaybeFilterValue): string {
    if (field.type === 'boolean') return field.label;
    if (value !== null && typeof value === 'object') {
      const dt = isDateTimeField(field);
      if (value.mode === 'contains') return `${field.label}: содержит "${value.value}"`;
      if (value.mode === 'equals' || value.mode === undefined)
        return `${field.label}: ${dt ? formatDatetime(String(value.value ?? '')) : (value.value ?? '')}`;
      if (value.mode === 'from')
        return `${field.label}: с ${dt ? formatDatetime(String(value.from ?? '')) : (value.from ?? '...')}`;
      if (value.mode === 'to')
        return `${field.label}: до ${dt ? formatDatetime(String(value.to ?? '')) : (value.to ?? '...')}`;
      if (value.mode === 'interval') {
        const parts: string[] = [];
        if (value.from !== undefined && value.from !== null)
          parts.push(`с ${dt ? formatDatetime(String(value.from)) : value.from}`);
        if (value.to !== undefined && value.to !== null)
          parts.push(`до ${dt ? formatDatetime(String(value.to)) : value.to}`);

        return `${field.label}: ${parts.join(' ')}`;
      }

      return `${field.label}: ${JSON.stringify(value)}`;
    }
    if (typeof value === 'string' && isDateTimeField(field)) {
      return `${field.label}: ${formatDatetime(value)}`;
    }
    if ((field.type === 'select' || field.type === 'active') && field.options) {
      const option = field.options.find((opt) => opt.value === value);
      if (option) return `${field.label}: ${option.label}`;
    }

    return `${field.label}: ${value}`;
  }

  private stringPredicate(value: MaybeFilterValue): (rowValue: unknown) => boolean {
    const parsed = parseStringValue(value);
    if (!parsed) return () => false;
    const { mode, value: needle } = parsed;
    const q = needle.toLowerCase();

    return (rowValue) => {
      const s = String(rowValue ?? '').toLowerCase();

      return mode === 'equals' ? s === q : s.includes(q);
    };
  }

  private numberPredicate(value: MaybeFilterValue): (rowValue: unknown) => boolean {
    const parsed = parseNumberValue(value);
    if (!parsed) return () => false;

    return (rowValue) => {
      const n = Number(rowValue);
      if (Number.isNaN(n)) return false;
      if (parsed.mode === 'equals') return n === parsed.value;
      if (parsed.mode === 'from') return n >= parsed.from;
      if (parsed.mode === 'to') return n <= parsed.to;

      return n >= parsed.from && n <= parsed.to;
    };
  }

  private dateTimePredicate(value: MaybeFilterValue): (rowValue: unknown) => boolean {
    const parsed = parseDateTimeValue(value);
    if (!parsed) return () => false;

    return (rowValue) => {
      const t = toTime(rowValue);
      if (t === null) return false;
      if (parsed.mode === 'equals') {
        const target = toTime(parsed.value);
        if (target === null) return false;

        return sameDay(t, target);
      }
      if (parsed.mode === 'from') {
        if (parsed.from === undefined) return false;
        const from = toTime(parsed.from);

        return from !== null && t >= from;
      }
      if (parsed.mode === 'to') {
        if (parsed.to === undefined) return false;
        const to = toTime(parsed.to);

        return to !== null && t <= to;
      }

      const fromTime = parsed.from !== undefined ? toTime(parsed.from) : null;
      const toTimeValue = parsed.to !== undefined ? toTime(parsed.to) : null;

      return (fromTime === null || t >= fromTime) && (toTimeValue === null || t <= toTimeValue);
    };
  }
}

type ParsedString = { mode: 'equals' | 'contains'; value: string };
type ParsedNumber =
  | { mode: 'equals'; value: number }
  | { mode: 'from'; from: number }
  | { mode: 'to'; to: number }
  | { mode: 'interval'; from: number; to: number };
type ParsedDateTime =
  | { mode: 'equals'; value: string }
  | { mode: 'from'; from: string }
  | { mode: 'to'; to: string }
  | { mode: 'interval'; from?: string; to?: string };

function parseStringValue(value: MaybeFilterValue): ParsedString | null {
  if (typeof value === 'string') return value ? { mode: 'contains', value } : null;
  if (
    value !== null &&
    typeof value === 'object' &&
    (value.mode === 'equals' || value.mode === 'contains') &&
    value.value !== undefined
  ) {
    return { mode: value.mode, value: String(value.value) };
  }

  return null;
}

function parseNumberValue(value: MaybeFilterValue): ParsedNumber | null {
  if (typeof value === 'number') return { mode: 'equals', value };
  if (typeof value === 'string') return value === '' ? null : { mode: 'equals', value: Number(value) };
  if (value !== null && typeof value === 'object') {
    if (value.mode === 'equals' && typeof value.value === 'number') return { mode: 'equals', value: value.value };
    if (value.mode === 'from' && typeof value.from === 'number') return { mode: 'from', from: value.from };
    if (value.mode === 'to' && typeof value.to === 'number') return { mode: 'to', to: value.to };
    if (value.mode === 'interval') {
      return {
        mode: 'interval',
        from: typeof value.from === 'number' ? value.from : -Infinity,
        to: typeof value.to === 'number' ? value.to : Infinity,
      };
    }
  }

  return null;
}

function parseDateTimeValue(value: MaybeFilterValue): ParsedDateTime | null {
  if (typeof value === 'string') return value ? { mode: 'equals', value } : null;
  if (value !== null && typeof value === 'object') {
    if (value.mode === 'equals' && typeof value.value === 'string') return { mode: 'equals', value: value.value };
    if (value.mode === 'from' && typeof value.from === 'string') return { mode: 'from', from: value.from };
    if (value.mode === 'to' && typeof value.to === 'string') return { mode: 'to', to: value.to };
    if (value.mode === 'interval') {
      return {
        mode: 'interval',
        from: typeof value.from === 'string' ? value.from : undefined,
        to: typeof value.to === 'string' ? value.to : undefined,
      };
    }
  }

  return null;
}

function toTime(v: unknown): number | null {
  if (typeof v !== 'string' && typeof v !== 'number') return null;
  const t = new Date(v).getTime();

  return Number.isNaN(t) ? null : t;
}

function sameDay(a: number, b: number): boolean {
  const da = new Date(a);
  const db = new Date(b);

  return da.getFullYear() === db.getFullYear() && da.getMonth() === db.getMonth() && da.getDate() === db.getDate();
}

function compareScalar(a: unknown, b: unknown): number {
  if (typeof a === 'boolean' && typeof b === 'boolean') return a === b ? 0 : a ? 1 : -1;
  if (typeof a === 'number' && typeof b === 'number') return a - b;

  return String(a).localeCompare(String(b));
}

function isDateTimeField(field: FilterField): boolean {
  return field.type === 'datetime' || field.type === 'date';
}
