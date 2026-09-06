import { RevisionFileCatalogSyncService } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileCatalogSyncService';
import { getKeywordApi, getMechanicApi } from '@/modules/Roleplay/Rule/init';

export const revisionFileCatalogSyncService = new RevisionFileCatalogSyncService(getKeywordApi, getMechanicApi);
