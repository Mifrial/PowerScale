/**
 * Общий сброс кубов преимуществ/помех: низкие грани лучше, поэтому преимущество
 * убирает самые большие, помеха — самые маленькие. Тот же алгоритм, что у механики
 * `advantage_disadvantage` в RollEngine.
 */
export function applyAdvantageDrop(rolls: number[], net: number): { kept: number[]; dropped: number[] } {
  const count = Math.abs(net);
  if (count === 0) return { kept: [...rolls], dropped: [] };
  const sorted = [...rolls].sort((a, b) => (net > 0 ? b - a : a - b));
  const droppedCount = Math.min(count, sorted.length);

  return {
    dropped: sorted.slice(0, droppedCount),
    kept: sorted.slice(droppedCount),
  };
}
