import type { LoreArticle } from '@/modules/Roleplay/Lore/Dto/LoreArticle';
import type { LoreArticleLink } from '@/modules/Roleplay/Lore/Dto/LoreArticleLink';
import type { LoreBacklink } from '@/modules/Roleplay/Lore/Dto/LoreBacklink';
import type { LoreReader } from '@/modules/Roleplay/Lore/Dto/LoreReader';

/** Статья со ссылками и читателями. */
export interface LoreArticleDetail extends LoreArticle {
  canEdit: boolean;
  links: LoreArticleLink[];
  backlinks: LoreBacklink[];
  readers: LoreReader[];
}
