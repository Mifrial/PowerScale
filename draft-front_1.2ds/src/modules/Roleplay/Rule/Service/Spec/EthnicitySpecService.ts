import type { EthnicitySpec } from '@/modules/Roleplay/Rule/Dto/EthnicitySpec';
import type { EthnicityUsage } from '@/modules/Roleplay/Rule/Dto/EthnicityUsage';
import type { EthnicityRole } from '@/modules/Roleplay/Rule/Enum/EthnicityRole';

/** Пустая и нормализованная спека народности. */
export class EthnicitySpecService {
  createEmpty(): EthnicitySpec {
    return {
      type: 'ethnicity',
      role: 'people',
      parent_code: null,
      race_codes: [],
      language_codes: [],
      usages: [],
    };
  }

  resolve(spec: unknown): EthnicitySpec {
    if (!spec || typeof spec !== 'object' || !('type' in spec) || spec.type !== 'ethnicity') {
      return this.createEmpty();
    }
    const value = spec as Partial<EthnicitySpec>;
    const role: EthnicityRole = value.role === 'stock' ? 'stock' : 'people';
    const parent = typeof value.parent_code === 'string' && value.parent_code !== '' ? value.parent_code : null;
    const races = this.uniqueCodes(value.race_codes);
    const languages = this.uniqueCodes(value.language_codes);
    const usages = this.normalizeUsages(value.usages, languages);

    return {
      type: 'ethnicity',
      role,
      parent_code: parent,
      race_codes: races,
      language_codes: languages,
      usages,
    };
  }

  private uniqueCodes(codes: unknown): string[] {
    if (!Array.isArray(codes)) return [];

    return [...new Set(codes.filter((code): code is string => typeof code === 'string' && code !== ''))];
  }

  private normalizeUsages(usages: unknown, languageCodes: string[]): EthnicityUsage[] {
    const allowed = new Set(languageCodes);
    if (!Array.isArray(usages)) {
      return languageCodes.map((language_code) => ({ language_code, script_codes: [] }));
    }
    const byLanguage = new Map<string, string[]>();
    for (const entry of usages) {
      if (!entry || typeof entry !== 'object') continue;
      const row = entry as Partial<EthnicityUsage>;
      if (typeof row.language_code !== 'string' || !allowed.has(row.language_code)) continue;
      const scripts = this.uniqueCodes(row.script_codes);
      byLanguage.set(row.language_code, scripts);
    }

    return languageCodes.map((language_code) => ({
      language_code,
      script_codes: byLanguage.get(language_code) ?? [],
    }));
  }
}
