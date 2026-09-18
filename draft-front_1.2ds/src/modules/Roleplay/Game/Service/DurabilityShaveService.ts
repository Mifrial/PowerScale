import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/**
 * Срез надёжности доспеха с единиц броска попадания (Удар в сочленение).
 */
export class DurabilityShaveService {
  oneCount(rolls: number[] | undefined): number {
    return (rolls ?? []).filter((value) => value === 1).length;
  }

  shave(oneCount: number, shortWeapon: boolean, hit: boolean): number {
    if (!hit || oneCount <= 0) return 0;

    return oneCount + (shortWeapon ? 1 : 0);
  }

  isShortWeapon(itemRule: Rule | null | undefined, keywords: { id: number; code: string }[]): boolean {
    if (!itemRule) return false;
    const shortId = keywords.find((keyword) => keyword.code === 'short')?.id;
    if (shortId == null) return false;

    return (itemRule.keywordIds ?? []).includes(shortId);
  }
}
