/** HTML заметки в плоский текст для превью мока. */
export class NoteTextService {
  toText(html: string): string {
    if (typeof DOMParser === 'undefined') {
      return html
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/\s+/g, ' ')
        .trim();
    }

    const body = new DOMParser().parseFromString(html, 'text/html').body;

    return (body.textContent ?? '')
      .replace(/\u00a0/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }
}
