export function formatUnix(unix: number | null | undefined, withTime = false): string {
  if (unix == null) return '—';
  const date = new Date(unix * 1000);
  if (Number.isNaN(date.getTime())) return '—';
  const datePart = date.toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
  if (!withTime) return datePart;

  return datePart + ' ' + date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}
