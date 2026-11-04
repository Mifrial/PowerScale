import { getFileApi } from '@/modules/Core/File/init';

/**
 * URL байтов для `<img>`; архивная строка байты не просит.
 *
 * @param publicId Внешний id.
 * @param archived Файл в архиве.
 *
 * @returns URL или пустая строка.
 */
export function fileImageSource(publicId: string, archived: boolean): string {
  if (archived || !publicId) return '';

  return getFileApi().getViewUrl(publicId);
}
