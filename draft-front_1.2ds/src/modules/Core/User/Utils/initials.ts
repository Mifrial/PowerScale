export function initials(name?: string, surname?: string): string {
  const first = name?.[0] ?? '';
  const second = surname?.[0] ?? '';

  return (first + second).toUpperCase() || '?';
}
