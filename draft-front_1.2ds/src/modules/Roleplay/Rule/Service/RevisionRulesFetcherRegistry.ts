import type { IRevisionRulesFetcher } from '@/modules/Roleplay/Rule/Interface/IRevisionRulesFetcher';

export class RevisionRulesFetcherRegistry {
  private fetcher: IRevisionRulesFetcher | null = null;

  register(fetcher: IRevisionRulesFetcher): void {
    this.fetcher = fetcher;
  }

  get(): IRevisionRulesFetcher | null {
    return this.fetcher;
  }
}
