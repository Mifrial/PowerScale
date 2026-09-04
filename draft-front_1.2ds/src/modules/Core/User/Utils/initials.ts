export function initials(name?: string, surname?: string): string {
  const first = letterOf(name);
  const second = letterOf(surname);

  return (first + second).toUpperCase() || '?';
}

function letterOf(part?: string): string {
  const trimmed = part?.trim() ?? '';
  if (trimmed === '') {
    return '';
  }

  return trimmed[0];
}
