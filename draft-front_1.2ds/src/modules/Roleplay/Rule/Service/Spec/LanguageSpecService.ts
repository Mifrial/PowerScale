import type { LanguageSpec } from '@/modules/Roleplay/Rule/Dto/LanguageSpec';
import type { LanguageRole } from '@/modules/Roleplay/Rule/Enum/LanguageRole';

/** Пустая и нормализованная спека языка. */
export class LanguageSpecService {
  createEmpty(): LanguageSpec {
    return { type: 'language', role: 'language', parent_code: null, script_codes: [] };
  }

  resolve(spec: unknown): LanguageSpec {
    if (!spec || typeof spec !== 'object' || !('type' in spec) || spec.type !== 'language') {
      return this.createEmpty();
    }
    const value = spec as Partial<LanguageSpec>;
    const role: LanguageRole = value.role === 'stock' ? 'stock' : 'language';
    const parent = typeof value.parent_code === 'string' && value.parent_code !== '' ? value.parent_code : null;
    const scripts = Array.isArray(value.script_codes)
      ? [...new Set(value.script_codes.filter((code): code is string => typeof code === 'string' && code !== ''))]
      : [];

    return { type: 'language', role, parent_code: parent, script_codes: scripts };
  }
}
