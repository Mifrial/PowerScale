/** Мягкое форматирование размера файла для UI. */
export function formatFileSize(size: number): string {
  if (!Number.isFinite(size) || size < 0) return '—';
  if (size < 1024) return `${size} Б`;
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} КБ`;

  return `${(size / (1024 * 1024)).toFixed(1)} МБ`;
}
