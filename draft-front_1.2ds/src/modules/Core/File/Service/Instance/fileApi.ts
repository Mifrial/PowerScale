import type { IFileApi } from '@/modules/Core/File/Interface/IFileApi';
import { getFileApi } from '@/modules/Core/File/init';

/** Ленивый доступ к текущей реализации IFileApi (mock или real). */
export function getFileApiInstance(): IFileApi {
  return getFileApi();
}
