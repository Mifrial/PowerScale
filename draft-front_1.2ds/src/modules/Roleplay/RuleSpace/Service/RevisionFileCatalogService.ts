import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';

/**
 * Свежие list-справочники для файла ревизии, без Pinia.
 */
export class RevisionFileCatalogService {
  constructor(
    private readonly getKeywords: (signal?: AbortSignal) => Promise<Keyword[]>,
    private readonly getMechanics: (signal?: AbortSignal) => Promise<Mechanic[]>,
  ) {}

  async load(signal?: AbortSignal): Promise<{ keywords: Keyword[]; mechanics: Mechanic[] }> {
    const [keywords, mechanics] = await Promise.all([this.getKeywords(signal), this.getMechanics(signal)]);

    return { keywords, mechanics };
  }
}
