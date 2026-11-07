/** Смешивает два `#rrggbb`: `ratio` = 0 даёт `from`, 1 — `to`. */
export function mixHex(from: string, to: string, ratio: number): string {
  const left = parseHex(from);
  const right = parseHex(to);
  if (!left || !right) return from;
  const share = Math.min(1, Math.max(0, ratio));
  const channels = left.map((value, index) => Math.round(value + ((right[index] ?? value) - value) * share));

  return `#${channels.map((value) => value.toString(16).padStart(2, '0')).join('')}`;
}

/** Превращает `#rrggbb` в `rgba(...)` с заданной непрозрачностью. */
export function withAlpha(color: string, alpha: number): string {
  const parsed = parseHex(color);
  if (!parsed) return color;
  const opacity = Math.min(1, Math.max(0, alpha));

  return `rgba(${parsed[0]}, ${parsed[1]}, ${parsed[2]}, ${opacity})`;
}

/** Разбирает `#rrggbb` в тройку каналов; иное — null. */
export function parseHex(color: string): [number, number, number] | null {
  const match = /^#([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/.exec(color.trim());
  if (!match) return null;

  return [Number.parseInt(match[1], 16), Number.parseInt(match[2], 16), Number.parseInt(match[3], 16)];
}
