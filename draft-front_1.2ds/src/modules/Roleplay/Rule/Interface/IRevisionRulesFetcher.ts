import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** Срез правил ревизии: регистрирует Space, вызывает Rule (слайдер), без импорта Space из Rule. */
export interface IRevisionRulesFetcher {
  fetchRules(spaceId: number, revision: number, signal?: AbortSignal): Promise<Rule[]>;
}
