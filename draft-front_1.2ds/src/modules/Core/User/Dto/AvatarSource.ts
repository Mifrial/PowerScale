/** Источник аватара: уже загруженный файл пользователя. */
export interface AvatarSource {
  publicId: string;
  originalName: string;
  width: number | null;
  height: number | null;
}
