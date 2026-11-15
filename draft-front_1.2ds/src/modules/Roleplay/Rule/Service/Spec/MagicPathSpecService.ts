import type { MagicPathSpec } from '@/modules/Roleplay/Rule/Dto/MagicPath/MagicPathSpec';

/** Пустая спека пути волшебства для нового правила. */
export class MagicPathSpecService {
  createEmpty(): MagicPathSpec {
    return {
      type: 'magic_path',
      check_code: null,
      cast_check_code: null,
      power_characteristic_code: null,
      control_characteristic_code: null,
      study_cost: null,
      includes_path_codes: [],
      no_control: false,
      recorded_casting: false,
      active_spell_limit: null,
    };
  }

  /**
   * Нормализует спеку пути. `type` в опубликованном контенте может отсутствовать:
   * тип правила уже равен `magic_path`, поэтому опираемся на форму, а не на метку.
   */
  resolve(spec: unknown): MagicPathSpec {
    if (!spec || typeof spec !== 'object') {
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
      cast_check_code: value.cast_check_code ?? null,
      power_characteristic_code: value.power_characteristic_code ?? null,
      control_characteristic_code: value.control_characteristic_code ?? null,
      study_cost: value.study_cost ?? null,
      includes_path_codes: includes,
      no_control: value.no_control === true,
      recorded_casting: value.recorded_casting === true,
      active_spell_limit:
        typeof value.active_spell_limit === 'number' &&
        Number.isInteger(value.active_spell_limit) &&
        value.active_spell_limit > 0
          ? value.active_spell_limit
          : null,
    };
  }
}
