export function initials(name?: string, surname?: string): string {
  const first = name?.[0] ?? '';
  const second = surname?.[0] ?? '';

  return (first + second).toUpperCase() || '?';
}

export function displayName(name?: string, surname?: string, login?: string): string {
  const parts = [name, surname].filter(Boolean);

  return parts.join(' ') || login || '';
}
