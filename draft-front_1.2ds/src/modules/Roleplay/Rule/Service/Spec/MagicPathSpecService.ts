import type { MagicPathSpec } from '@/modules/Roleplay/Rule/Dto/MagicPath/MagicPathSpec';

/** Пустая спека пути волшебства для нового правила. */
export class MagicPathSpecService {
  createEmpty(): MagicPathSpec {
    return { type: 'magic_path', check_code: null, study_cost: null, includes_path_codes: [] };
  }

  resolve(spec: unknown): MagicPathSpec {
    if (!spec || typeof spec !== 'object' || !('type' in spec) || spec.type !== 'magic_path') {
      return this.createEmpty();
    }
    const value = spec as MagicPathSpec;
    const includes = Array.isArray(value.includes_path_codes)
      ? [
          ...new Set(
            value.includes_path_codes.filter((code): code is string => typeof code === 'string' && code !== ''),
          ),
        ]
      : [];

    return {
      type: 'magic_path',
      check_code: value.check_code ?? null,
      study_cost: value.study_cost ?? null,
      includes_path_codes: includes,
    };
  }
}
