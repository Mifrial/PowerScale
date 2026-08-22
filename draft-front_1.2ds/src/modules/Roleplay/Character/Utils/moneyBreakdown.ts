/** Размен денег (всегда в гм) на крупные номиналы: 1 гз = 10 гс = 100 гм. */
export function moneyBreakdownLabel(total: number): string {
  if (total <= 0) return `${total} гм`;
  const gold = Math.floor(total / 100);
  const silver = Math.floor((total % 100) / 10);
  const copper = total % 10;
  const parts: string[] = [];
  if (gold > 0) parts.push(`${gold} гз`);
  if (silver > 0) parts.push(`${silver} гс`);
  if (copper > 0 || parts.length === 0) parts.push(`${copper} гм`);

  return parts.join(' · ');
}
