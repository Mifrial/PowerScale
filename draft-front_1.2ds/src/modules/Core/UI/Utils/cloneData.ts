import { isRef, toRaw } from 'vue';

function unwrap(value: unknown): unknown {
  if (value == null || typeof value !== 'object') return value;
  if (isRef(value)) return unwrap(value.value);
  const raw = toRaw(value);
  if (Array.isArray(raw)) return raw.map(unwrap);

  return Object.fromEntries(Object.entries(raw).map(([k, v]) => [k, unwrap(v)]));
}

export function cloneData<T>(value: T): T {
  return structuredClone(unwrap(value)) as T;
}
