import { RevisionFileCatalogSyncService } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileCatalogSyncService';
import { getKeywordApi } from '@/modules/Roleplay/Keyword/init';
import { getMechanicApi } from '@/modules/Roleplay/Mechanic/init';

export const revisionFileCatalogSyncService = new RevisionFileCatalogSyncService(getKeywordApi, getMechanicApi);
