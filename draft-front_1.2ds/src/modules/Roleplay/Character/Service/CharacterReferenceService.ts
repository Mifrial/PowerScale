import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ResolvedReference } from '@/modules/Roleplay/Character/Dto/Overview/ResolvedReference';

/**
 * Резолвер ссылок персонажа на правила ревизии. Персонаж хранит только ruleId;
 * имя/тип/спека правила берутся из ревизии, а не копируются в версию.
 * Экземпляр привязан к одной ревизии (неизменяемый набор правил).
 */
export class CharacterReferenceService {
  private readonly byId = new Map<string, Rule>();
  private readonly byCode = new Map<string, Rule>();

  constructor(
    rules: Rule[],
    private readonly spaceCode: string,
    private readonly rulesRevision: number,
  ) {
    for (const rule of rules) {
      this.byId.set(rule.id, rule);
      this.byCode.set(rule.code, rule);
    }
  }

  ruleById(ruleId: string): Rule | null {
    return this.byId.get(ruleId) ?? null;
  }

  ruleByCode(code: string): Rule | null {
    return this.byCode.get(code) ?? null;
  }

  /** Все правила ревизии (для диспетчера механик). */
  rules(): Rule[] {
    return [...this.byId.values()];
  }

  /**
   * Ссылка на правило внутри ревизии. Route Space-контекста допускает только числовой ctx,
   * поэтому ревизия передаётся числом, а не «v{N}».
   */
  href(ruleId: string): string {
    return `/space/${this.spaceCode}/${this.rulesRevision}/rules/${ruleId}`;
  }

  /**
   * Разрешает ссылку в display-информацию. Отсутствующее правило → фолбэк по коду
   * (контракт на случай реального бэка, который может ссылаться кодом); иначе — неразрешённая
   * ссылка с именем-заглушкой, чтобы карточка не падала.
   */
  resolve(ruleId: string): ResolvedReference {
    const byId = this.byId.get(ruleId);
    if (byId) {
      return { ruleId, name: byId.name, href: this.href(byId.id), isResolved: true, rule: byId };
    }

    const byCode = this.byCode.get(ruleId);
    if (byCode) {
      return { ruleId, name: byCode.name, href: this.href(byCode.id), isResolved: true, rule: byCode };
    }

    return { ruleId, name: ruleId, href: null, isResolved: false, rule: null };
  }
}
