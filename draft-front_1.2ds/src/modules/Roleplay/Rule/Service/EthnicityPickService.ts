import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { EthnicitySpecService } from '@/modules/Roleplay/Rule/Service/Spec/EthnicitySpecService';
import type { EthnicityTreeService } from '@/modules/Roleplay/Rule/Service/EthnicityTreeService';
import type { LanguageRelatednessService } from '@/modules/Roleplay/Rule/Service/LanguageRelatednessService';
import { OPTION_BRIEF_MAX_LENGTH } from '@/modules/Roleplay/Rule/Constant/Ethnicity/OPTION_BRIEF_MAX_LENGTH';

/** Опции народности и родного языка на листе: пустой race_codes не режет. */
export class EthnicityPickService {
  constructor(
    private readonly ethnicitySpec: EthnicitySpecService,
    private readonly ethnicityTree: EthnicityTreeService,
    private readonly languages: LanguageRelatednessService,
  ) {}

  ethnicityOptions(
    rules: Rule[],
    raceCode: string | null,
  ): { code: string; name: string; preferred: boolean; subtitle: string }[] {
    return this.ethnicityTree
      .pickableOptions(rules)
      .map((option) => {
        const rule = rules.find((entry) => entry.code === option.code);
        const races = rule ? this.ethnicitySpec.resolve(rule.spec).race_codes : [];
        const preferred = races.length === 0 || (raceCode != null && races.includes(raceCode));

        return { ...option, preferred, subtitle: this.briefOf(rule?.description ?? '') };
      })
      .sort(
        (left, right) => Number(right.preferred) - Number(left.preferred) || left.name.localeCompare(right.name, 'ru'),
      );
  }

  nativeLanguageOptions(
    rules: Rule[],
    ethnicityCode: string | null,
  ): { code: string; name: string; preferred: boolean; subtitle: string }[] {
    const speakable = this.languages.speakableOptions(rules);
    const subtitleOf = (code: string): string =>
      this.briefOf(rules.find((rule) => rule.type === 'language' && rule.code === code)?.description ?? '');
    const preferredOrder: string[] = [];
    if (ethnicityCode) {
      const ethnicity = rules.find((rule) => rule.type === 'ethnicity' && rule.code === ethnicityCode);
      preferredOrder.push(...(ethnicity ? this.ethnicitySpec.resolve(ethnicity.spec).language_codes : []));
    }
    const preferredCodes = new Set(preferredOrder);
    const preferred = preferredOrder
      .map((code) => speakable.find((option) => option.code === code))
      .filter((option): option is { code: string; name: string } => option != null)
      .map((option) => ({ ...option, preferred: true, subtitle: subtitleOf(option.code) }));
    const rest = speakable
      .filter((option) => !preferredCodes.has(option.code))
      .map((option) => ({ ...option, preferred: false, subtitle: subtitleOf(option.code) }));

    return [...preferred, ...rest];
  }

  comboItems(
    options: readonly { code: string; name: string; preferred: boolean; subtitle?: string }[],
  ): { title: string; value?: string; subtitle?: string; type?: 'divider' }[] {
    const toItem = (option: { code: string; name: string; subtitle?: string }) => ({
      title: option.name,
      value: option.code,
      subtitle: option.subtitle,
    });
    const preferred = options.filter((option) => option.preferred).map(toItem);
    const rest = options.filter((option) => !option.preferred).map(toItem);
    if (preferred.length > 0 && rest.length > 0) return [...preferred, { type: 'divider' }, ...rest];

    return [...preferred, ...rest];
  }

  matchOption(
    raw: unknown,
    options: readonly { code: string; name: string }[],
    aliasesOf: (option: { code: string; name: string }) => string[],
  ): { code: string | null; text: string | null } {
    const token = this.optionToken(raw);
    if (!token) return { code: null, text: null };
    const option = options.find((entry) => aliasesOf(entry).includes(token));

    return option ? { code: option.code, text: null } : { code: null, text: token };
  }

  displayLabel(
    code: string | null,
    text: string | null,
    options: readonly { code: string; name: string }[],
  ): string | null {
    if (code) return options.find((option) => option.code === code)?.name ?? code;

    return text?.trim() || null;
  }

  matchesQuery(item: { title?: string; subtitle?: string }, query: string): boolean {
    const needle = query.trim().toLowerCase();
    if (!needle) return true;
    const haystack = `${item.title ?? ''} ${item.subtitle ?? ''}`.toLowerCase();

    return haystack.includes(needle);
  }

  briefOf(html: string): string {
    const first = html.match(/<p[^>]*>([\s\S]*?)<\/p>/i)?.[1] ?? html;
    const text = first
      .replace(/<[^>]+>/g, ' ')
      .replace(/&nbsp;/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
    if (text.length <= OPTION_BRIEF_MAX_LENGTH) return text;

    return `${text.slice(0, OPTION_BRIEF_MAX_LENGTH - 1).trim()}…`;
  }

  private optionToken(raw: unknown): string | null {
    if (raw == null || raw === '') return null;
    if (typeof raw === 'string' || typeof raw === 'number') return String(raw).trim() || null;
    if (typeof raw !== 'object') return null;
    if ('value' in raw) {
      const value = raw.value;
      if (typeof value === 'string' || typeof value === 'number') return String(value).trim() || null;
    }
    if ('title' in raw && typeof raw.title === 'string') return raw.title.trim() || null;

    return null;
  }
}
