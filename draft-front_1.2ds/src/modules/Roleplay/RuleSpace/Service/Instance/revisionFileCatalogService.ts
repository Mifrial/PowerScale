import { RevisionFileCatalogService } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileCatalogService';
import { getKeywordApi } from '@/modules/Roleplay/Keyword/init';
import { getMechanicApi } from '@/modules/Roleplay/Rule/init';

export const revisionFileCatalogService = new RevisionFileCatalogService(
  (signal) => getKeywordApi().getKeywords(signal),
  (signal) => getMechanicApi().getMechanics(signal),
);
