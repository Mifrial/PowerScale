import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { LanguageSpecService } from '@/modules/Roleplay/Rule/Service/Spec/LanguageSpecService';
import type { ScriptSpecService } from '@/modules/Roleplay/Rule/Service/Spec/ScriptSpecService';

/** Лестницы письменности и какие знаки покрывают язык. */
export class ScriptLiteracyService {
  constructor(
    private readonly languageSpec: LanguageSpecService,
    private readonly scriptSpec: ScriptSpecService,
    private readonly alphabeticLadder: readonly number[],
    private readonly logographicLadder: readonly number[],
  ) {}

  ladderForDomain(rules: Rule[], domain: string | null | undefined): number[] | null {
    if (!domain) return [...this.alphabeticLadder];
    const script = rules.find((rule) => rule.type === 'script' && (rule.code === domain || rule.name === domain));
    if (!script) return [...this.alphabeticLadder];
    const kind = this.scriptSpec.resolve(script.spec).kind;

    return kind === 'logographic' ? [...this.logographicLadder] : [...this.alphabeticLadder];
  }

  /** Код/имя языка → коды письменностей, которыми его пишут. */
  languageScriptIndex(rules: Rule[]): Map<string, Set<string>> {
    const index = new Map<string, Set<string>>();
    for (const rule of rules) {
      if (rule.type !== 'language') continue;
      const codes = this.languageSpec.resolve(rule.spec).script_codes;
      if (codes.length === 0) continue;
      const set = new Set(codes);
      index.set(rule.code, set);
      index.set(rule.name, set);
    }

    return index;
  }
}
