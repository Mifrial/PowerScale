/** Короткое имя ресурса для стоимостей шагов процесса («3 ОД» вместо «Очки Действий: 3»). */
export function resourceShortName(code: string): string | null {
  return { 'action-points': 'ОД' }[code] ?? null;
}
