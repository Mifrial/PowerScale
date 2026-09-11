import { EditorMagicPathViewsService } from '@/modules/Roleplay/Character/Service/EditorMagicPathViewsService';
import { magicStudyUnlockService } from '@/modules/Roleplay/Character/Service/Instance/magicStudyUnlockService';

export const editorMagicPathViewsService = new EditorMagicPathViewsService(magicStudyUnlockService);
