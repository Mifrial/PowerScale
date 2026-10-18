const UNIX_SECONDS_LIMIT = 1e12;

export function formatDatetime(iso: string): string {
  try {
    const date = parseDatetime(iso);
    if (date === null) return iso;

    return (
      date.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      }) +
      ' ' +
      date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
    );
  } catch {
    return iso;
  }
}

function parseDatetime(value: string): Date | null {
  const date = isUnixSeconds(value) ? new Date(Number(value) * 1000) : new Date(value);
  if (isNaN(date.getTime())) return null;

  return date;
}

function isUnixSeconds(value: string): boolean {
  if (!/^\d+$/.test(value)) return false;

  return Number(value) < UNIX_SECONDS_LIMIT;
}
