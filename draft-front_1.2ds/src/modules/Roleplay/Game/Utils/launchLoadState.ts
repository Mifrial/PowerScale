const LOAD_FAILED = 'Не удалось загрузить состояние боя';

export function resolveLaunchLoad<T extends Record<string, unknown>>(slices: {
  [K in keyof T]: { ok: true; value: T[K] } | { ok: false; cause: unknown };
}): { status: 'error'; message: string } | { status: 'ready'; values: { [K in keyof T]: T[K] } } {
  const keys = Object.keys(slices) as (keyof T)[];
  for (const key of keys) {
    const slice = slices[key];
    if (slice.ok) continue;
    const cause = slice.cause;
    const message = cause instanceof Error && cause.message.trim() !== '' ? cause.message : LOAD_FAILED;

    return { status: 'error', message };
  }

  const values = {} as { [K in keyof T]: T[K] };
  for (const key of keys) {
    const slice = slices[key];
    if (slice.ok) values[key] = slice.value;
  }

  return { status: 'ready', values };
}
