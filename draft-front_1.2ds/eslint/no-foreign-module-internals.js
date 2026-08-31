import path from 'node:path';

const PUBLIC_ROOT_FILES = new Set(['init', 'init.ts', 'routes', 'routes.ts']);
const PUBLIC_DIRS = new Set(['Dto', 'Interface', 'Enum', 'Mock']);
const OPEN_MODULES = new Set(['Core/Engine', 'Core/UI']);

function normalize(filePath) {
  return filePath.replaceAll('\\', '/');
}

function relFromSrc(filename) {
  const normalized = normalize(filename);
  const marker = '/src/';
  const index = normalized.lastIndexOf(marker);
  if (index < 0) return null;

  return normalized.slice(index + marker.length);
}

function isCompositionRoot(rel) {
  if (rel === 'main.ts') return true;
  if (rel.startsWith('shell/')) return true;
  if (rel.startsWith('router/')) return true;
  if (rel.startsWith('plugins/')) return true;

  return false;
}

function parseModule(rel) {
  const parts = rel.split('/');
  if (parts[0] !== 'modules' || parts.length < 3) return null;
  const group = parts[1];
  const moduleName = parts[2];
  if (moduleName.includes('.')) {
    return { kind: 'group-file', group };
  }

  return { kind: 'module', id: `${group}/${moduleName}` };
}

function remainderAfterModule(rel) {
  const parts = rel.split('/');
  if (parts[0] !== 'modules' || parts.length < 3) return [];

  return parts.slice(3);
}

function isPublicRemainder(remainder) {
  if (remainder.length === 0) return false;
  const first = remainder[0];
  if (PUBLIC_ROOT_FILES.has(first)) return true;
  if (PUBLIC_DIRS.has(first)) return true;

  return false;
}

function resolveSpec(filename, spec) {
  if (spec.startsWith('@/')) {
    const rel = relFromSrc(filename);
    if (!rel) return null;
    const srcDir = normalize(filename).slice(0, normalize(filename).length - rel.length);

    return normalize(path.posix.join(srcDir, spec.slice(2)));
  }
  if (spec.startsWith('.')) {
    return normalize(path.posix.join(path.posix.dirname(normalize(filename)), spec));
  }

  return null;
}

function checkSpec(context, node, spec) {
  if (typeof spec !== 'string') return;
  const filename = context.filename;
  const importerRel = relFromSrc(filename);
  if (!importerRel || isCompositionRoot(importerRel)) return;

  const resolved = resolveSpec(filename, spec);
  if (!resolved) return;
  const importedRel = relFromSrc(resolved);
  if (!importedRel || !importedRel.startsWith('modules/')) return;

  const importer = parseModule(importerRel);
  const imported = parseModule(importedRel);
  if (!imported || imported.kind !== 'module') return;
  if (OPEN_MODULES.has(imported.id)) return;
  if (importer?.kind === 'module' && importer.id === imported.id) return;

  if (isPublicRemainder(remainderAfterModule(importedRel))) return;

  context.report({
    node,
    messageId: 'foreignInternals',
    data: { spec },
  });
}

/** @type {import('eslint').Rule.RuleModule} */
const rule = {
  meta: {
    type: 'problem',
    docs: {
      description: 'Forbid importing another module’s internals (only init, Dto, Interface, Enum, Mock, routes).',
    },
    schema: [],
    messages: {
      foreignInternals:
        'Чужой модуль: импорт внутренностей запрещён ({{spec}}). Публично: init, Dto, Interface, Enum, Mock, routes.',
    },
  },
  create(context) {
    function visitSource(node) {
      if (node.source?.type === 'Literal') {
        checkSpec(context, node.source, node.source.value);
      }
    }

    return {
      ImportDeclaration: visitSource,
      ExportNamedDeclaration: visitSource,
      ExportAllDeclaration: visitSource,
      ImportExpression(node) {
        if (node.source?.type === 'Literal') {
          checkSpec(context, node.source, node.source.value);
        }
      },
    };
  },
};

export default rule;
