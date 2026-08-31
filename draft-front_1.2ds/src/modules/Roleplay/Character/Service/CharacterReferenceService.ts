import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ResolvedReference } from '@/modules/Roleplay/Character/Dto/Overview/ResolvedReference';

/**
 * Резолвер ссылок персонажа на правила ревизии. Персонаж хранит semantic `code`;
 * имя/тип/спека правила берутся из ревизии, а не копируются в версию.
 * Экземпляр привязан к одной ревизии (неизменяемый набор правил).
 */
export class CharacterReferenceService {
  private readonly byCode = new Map<string, Rule>();

  constructor(
    rules: Rule[],
    private readonly spaceCode: string,
    private readonly rulesRevision: number,
  ) {
    for (const rule of rules) {
      this.byCode.set(rule.code, rule);
    }
  }

  ruleByCode(code: string): Rule | null {
    return this.byCode.get(code) ?? null;
  }

  /** Все правила ревизии (для диспетчера механик). */
  rules(): Rule[] {
    return [...this.byCode.values()];
  }

  /**
   * Ссылка на правило внутри ревизии. Route Space-контекста допускает только числовой ctx,
   * поэтому ревизия передаётся числом, а не «v{N}».
   */
  href(ruleCode: string): string {
    const rule = this.byCode.get(ruleCode);
    const segment = encodeURIComponent(rule?.code ?? ruleCode);

    return `/space/${this.spaceCode}/${this.rulesRevision}/rules/${segment}`;
  }

  resolve(ruleCode: string): ResolvedReference {
    const rule = this.byCode.get(ruleCode);
    if (rule) {
      return { ruleCode, name: rule.name, href: this.href(rule.code), isResolved: true, rule };
    }

    return { ruleCode, name: ruleCode, href: null, isResolved: false, rule: null };
  }
}
