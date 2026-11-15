import type { LoreInlineRef } from '@/modules/Roleplay/Lore/Dto/Inline/LoreInlineRef';
import { isLoreCode } from '@/modules/Roleplay/Lore/Utils/loreSlug';

/** Сборка и разбор значения inline-ссылки `<worldCode>/<articleCode>`. */
export class LoreInlineValueService {
  build(worldCode: string, articleCode: string): string {
    return `${worldCode}/${articleCode}`;
  }

  parse(value: string): LoreInlineRef | null {
    const parts = value.split('/');
    if (parts.length !== 2) return null;
    const [worldCode, articleCode] = parts;
    if (!isLoreCode(worldCode) || !isLoreCode(articleCode)) return null;

    return { worldCode, articleCode };
  }
}
