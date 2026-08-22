// Синтаксис инлайн-токенов `[[type:param1,param2]]` в тексте сообщения (напр. `[[user:ivan]]`).
// global-флаг обязателен для matchAll; lastIndex оригинального регэкспа не используется.
export const INLINE_CONTENT_TOKEN_RE = /\[\[([a-z0-9_-]+):([^\]]+)\]\]/g;
