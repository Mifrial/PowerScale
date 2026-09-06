import { RevisionFileCatalogService } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileCatalogService';
import { getKeywordApi, getMechanicApi } from '@/modules/Roleplay/Rule/init';

export const revisionFileCatalogService = new RevisionFileCatalogService(
  (signal) => getKeywordApi().getKeywords(signal),
  (signal) => getMechanicApi().getMechanics(signal),
);
