export function displayName(name?: string, surname?: string, login?: string): string {
  const parts = [name, surname].filter(Boolean);

  return parts.join(' ') || login || '';
}
