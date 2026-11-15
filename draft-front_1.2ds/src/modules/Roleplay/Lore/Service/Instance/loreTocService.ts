import type { LoreTocItem } from '@/modules/Roleplay/Lore/Dto/LoreTocItem';

/** Оглавление статьи и якоря заголовков после `v-html`. */
class LoreTocService {
  build(root: HTMLElement | null): LoreTocItem[] {
    if (!root) return [];

    const items: LoreTocItem[] = [];
    const used = new Set<string>();
    root.querySelectorAll<HTMLElement>('h2, h3').forEach((heading, index) => {
      const title = heading.textContent?.trim() ?? '';
      if (title === '') return;

      const id = this.uniqueId(this.slug(title) || `section-${index + 1}`, used);
      heading.id = id;
      items.push({ id, level: heading.tagName === 'H2' ? 2 : 3, title });
    });

    return items;
  }

  private slug(title: string): string {
    return title
      .toLocaleLowerCase()
      .replace(/[^\p{L}\p{N}]+/gu, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 80);
  }

  private uniqueId(base: string, used: Set<string>): string {
    let candidate = base;
    let suffix = 2;
    while (used.has(candidate)) {
      candidate = `${base}-${suffix}`;
      suffix++;
    }

    used.add(candidate);

    return candidate;
  }
}

export const loreTocService = new LoreTocService();
