#!/usr/bin/env python3
"""Read-only repository inventory and review evidence collectors."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import subprocess
import sys
from collections import Counter, defaultdict
from pathlib import Path
from typing import Iterable


SCHEMA_VERSION = "1"
ANALYZER_VERSION = "0.1.0"
DEFAULT_EXCLUDES = {
    ".git",
    "node_modules",
    "vendor",
    "dist",
    "build",
    "coverage",
    ".vite",
    "__pycache__",
}
SOURCE_EXTENSIONS = {
    ".ts",
    ".tsx",
    ".vue",
    ".js",
    ".jsx",
    ".php",
    ".css",
    ".scss",
}
TEXT_EXTENSIONS = SOURCE_EXTENSIONS | {
    ".json",
    ".md",
    ".yaml",
    ".yml",
    ".xml",
    ".html",
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("command", choices=[
        "inventory",
        "dependencies",
        "public-surface",
        "rule-references",
        "duplication",
        "rule-matrix",
        "ui-inventory",
        "compliance",
        "manifest",
        "report",
    ])
    parser.add_argument("--root", type=Path, default=Path.cwd())
    parser.add_argument("--output", type=Path, default=Path("var/review"))
    parser.add_argument("--input", action="append", type=Path, default=[])
    return parser.parse_args()


def relative_path(root: Path, path: Path) -> str:
    return path.relative_to(root).as_posix()


def is_excluded(path: Path) -> bool:
    parts = path.parts
    if DEFAULT_EXCLUDES.intersection(parts):
        return True
    return any(
        part == "var" and index + 1 < len(parts) and parts[index + 1] == "review"
        for index, part in enumerate(parts)
    )


def iter_files(root: Path) -> Iterable[Path]:
    for path in sorted(root.rglob("*")):
        if path.is_file() and not is_excluded(path) and path.suffix.lower() in TEXT_EXTENSIONS:
            yield path


def read_text(path: Path) -> str:
    try:
        return path.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        return ""


def classify(path: Path) -> str:
    parts = path.parts
    path_string = "/".join(parts)
    if "test" in path.name.lower() or "__tests__" in parts:
        return "test"
    if path.suffix.lower() in {".md", ".yaml", ".yml"} or path_string.startswith("docs/"):
        return "documentation"
    if "Mock" in parts or "mock" in path.name.lower() or "fixtures" in parts:
        return "fixture"
    if path.name in {".env", "package-lock.json", "composer.lock"}:
        return "configuration"
    if path.suffix.lower() in SOURCE_EXTENSIONS:
        return "production"
    return "configuration"


def line_count(text: str) -> int:
    return text.count("\n") + (1 if text else 0)


def resolve_layer(path: Path) -> str:
    parts = set(path.parts)
    if "tests" in parts or "__tests__" in parts or "test" in path.name.lower():
        return "test"
    for layer in (
        "Action",
        "Component",
        "Constant",
        "Container",
        "Dto",
        "Enum",
        "Exception",
        "Interface",
        "Mock",
        "Page",
        "Repository",
        "Schema",
        "Service",
        "Setup",
        "Store",
        "Table",
        "Utils",
    ):
        if layer in parts:
            return layer
    return "root-or-other"


def extract_imports(text: str) -> list[str]:
    patterns = [
        r"""(?:import|export)\s+(?:type\s+)?[^;\n]*?\sfrom\s+['"]([^'"]+)['"]""",
        r"""import\s*\(\s*['"]([^'"]+)['"]\s*\)""",
        r"""require\(\s*['"]([^'"]+)['"]\s*\)""",
        r"""(?:include|require_once)\s+['"]([^'"]+)['"]""",
        r"""\buse\s+(Mifrial\\[^;]+)""",
    ]
    imports: list[str] = []
    for pattern in patterns:
        imports.extend(re.findall(pattern, text))
    return sorted(set(imports))


def extract_exports(text: str) -> list[str]:
    exports = re.findall(
        r"\bexport\s+(?:default\s+)?(?:class|interface|type|enum|const|function)\s+([A-Za-z_$][\w$]*)",
        text,
    )
    exports.extend(re.findall(r"\bexport\s*\{\s*([^}]+)\}", text))
    return sorted({
        part.strip().split(" as ")[-1]
        for value in exports
        for part in value.split(",")
        if part.strip()
    })


def base_record(root: Path, path: Path, text: str, test_paths: list[Path]) -> dict:
    return {
        "path": relative_path(root, path),
        "category": classify(path),
        "layer": resolve_layer(path),
        "module": resolve_module(relative_path(root, path))
        or resolve_backend_module(relative_path(root, path)),
        "extension": path.suffix.lower(),
        "lines": line_count(text),
        "bytes": path.stat().st_size,
        "imports": extract_imports(text),
        "exports": extract_exports(text),
        "relatedTests": [
            relative_path(root, test_path)
            for test_path in test_paths
            if path.stem.lower() in test_path.stem.lower()
        ],
    }


def write_json(output: Path, name: str, value: object) -> None:
    output.mkdir(parents=True, exist_ok=True)
    target = output / f"{name}.json"
    target.write_text(
        json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
    )


def artifact(command: str, value: object) -> dict:
    return {
        "schemaVersion": SCHEMA_VERSION,
        "analyzerVersion": ANALYZER_VERSION,
        "command": command,
        "records": value,
    }


def inventory(root: Path, output: Path) -> None:
    records = []
    category_lines: Counter[str] = Counter()
    paths = list(iter_files(root))
    test_paths = [
        path for path in paths
        if classify(path) == "test"
    ]
    for path in paths:
        text = read_text(path)
        record = base_record(root, path, text, test_paths)
        records.append(record)
        category_lines[record["category"]] += record["lines"]
    result = artifact("inventory", {
        "files": records,
        "summary": {
            "fileCount": len(records),
            "linesByCategory": dict(category_lines),
        },
    })
    write_json(output, "inventory", result)


def resolve_module(path: str) -> str | None:
    match = re.search(r"(?:^|/)(?:src/)?modules/([^/]+)/([^/]+)", path)
    return f"{match.group(1)}/{match.group(2)}" if match else None


def resolve_backend_module(path: str) -> str | None:
    match = re.search(r"(?:^|/)modules/([^/]+)/([^/]+)", path)
    return f"{match.group(1)}/{match.group(2)}" if match else None


def dependency_edges(root: Path) -> list[dict]:
    edges = []
    for path in iter_files(root):
        if path.suffix.lower() not in SOURCE_EXTENSIONS:
            continue
        source = relative_path(root, path)
        source_module = resolve_module(source) or resolve_backend_module(source)
        for imported in extract_imports(read_text(path)):
            target_match = re.search(r"(?:^@/|^\.*/)?(?:src/)?modules/([^/]+)/([^/]+)", imported)
            backend_match = re.match(r"Mifrial\\([^\\]+)\\([^\\]+)", imported)
            target_module = (
                f"{target_match.group(1)}/{target_match.group(2)}"
                if target_match
                else (
                    f"{backend_match.group(1)}/{backend_match.group(2)}"
                    if backend_match
                    else None
                )
            )
            if target_module and target_module != source_module:
                imported_layer = next(
                    (
                        segment for segment in (
                            "Action",
                            "Component",
                            "Dto",
                            "Interface",
                            "Page",
                            "Repository",
                            "Service",
                            "Store",
                            "Utils",
                        )
                        if f"/{segment}/" in f"/{imported}/"
                    ),
                    None,
                )
                source_layer = resolve_layer(Path(source))
                suspicious_reasons = []
                language = "frontend" if target_match else "backend"
                if language == "frontend" and any(
                    segment in imported
                    for segment in ("/Service/", "/Store/", "/Page/", "/Utils/", "/Composables/", "/Component/")
                ):
                    suspicious_reasons.append("frontend-internal-surface")
                if source_layer == "Dto" and imported_layer == "Service":
                    suspicious_reasons.append("dto-to-service")
                if source_layer == "Interface" and imported_layer in {"Service", "Repository", "Store"}:
                    suspicious_reasons.append("interface-to-implementation")
                edges.append({
                    "source": source,
                    "sourceModule": source_module,
                    "import": imported,
                    "targetModule": target_module,
                    "language": language,
                    "internal": any(segment in imported for segment in (
                        "/Service/", "/Store/", "/Page/", "/Utils/", "/Composables/",
                        "/Component/",
                    )),
                    "suspiciousReasons": suspicious_reasons,
                })
    return edges


def strongly_connected_components(grouped: dict[str, set[str]]) -> list[list[str]]:
    index = 0
    stack: list[str] = []
    on_stack: set[str] = set()
    indexes: dict[str, int] = {}
    low_links: dict[str, int] = {}
    components: list[list[str]] = []

    def visit(node: str) -> None:
        nonlocal index
        indexes[node] = index
        low_links[node] = index
        index += 1
        stack.append(node)
        on_stack.add(node)
        for target in grouped.get(node, set()):
            if target not in indexes:
                visit(target)
                low_links[node] = min(low_links[node], low_links[target])
            elif target in on_stack:
                low_links[node] = min(low_links[node], indexes[target])
        if low_links[node] == indexes[node]:
            component = []
            while True:
                target = stack.pop()
                on_stack.remove(target)
                component.append(target)
                if target == node:
                    break
            if len(component) > 1:
                components.append(sorted(component))

    for node in sorted(grouped):
        if node not in indexes:
            visit(node)
    return sorted(components)


def dependencies(root: Path, output: Path) -> None:
    edges = dependency_edges(root)
    grouped: dict[str, set[str]] = defaultdict(set)
    for edge in edges:
        if edge["sourceModule"] and edge["targetModule"]:
            grouped[edge["sourceModule"]].add(edge["targetModule"])
    graph = [
        {"source": source, "targets": sorted(targets)}
        for source, targets in sorted(grouped.items())
    ]
    write_json(output, "dependencies", artifact("dependencies", {
        "edges": edges,
        "moduleGraph": graph,
        "stronglyConnectedComponents": strongly_connected_components(grouped),
        "suspiciousEdges": [
            edge for edge in edges
            if edge["suspiciousReasons"]
        ],
        "frontendInternalCrossModuleEdges": [
            edge for edge in edges
            if edge["language"] == "frontend" and edge["internal"]
        ],
    }))


def evidence(record_id: str, finding_type: str, path: str, line: int, message: str, text: str) -> dict:
    return {
        "candidateId": record_id,
        "recordId": record_id,
        "status": "candidate",
        "type": finding_type,
        "path": path,
        "line": line,
        "symbol": None,
        "message": message,
        "evidence": text.strip()[:500],
        "confidence": "medium",
        "analyzerVersion": ANALYZER_VERSION,
    }


def public_surface(root: Path, output: Path) -> None:
    records = []
    for path in iter_files(root):
        relative = relative_path(root, path)
        if (
            path.name in {"init.ts", "routes.ts"}
            or "/Interface/" in f"/{relative}"
            or "/Dto/" in f"/{relative}"
            or "/Enum/" in f"/{relative}"
            or "/API/" in f"/{relative}"
            or "/Container/" in f"/{relative}"
            or "/Action/" in f"/{relative}"
        ):
            records.append({
                "path": relative,
                "module": resolve_module(relative) or resolve_backend_module(relative),
                "kind": (
                    "entrypoint"
                    if path.name in {"init.ts", "routes.ts"}
                    else "contract"
                ),
                "category": classify(path),
                "exports": extract_exports(read_text(path)),
            })
    internal_segments = (
        "/Service/",
        "/Store/",
        "/Repository/",
        "/Utils/",
        "/Composables/",
        "/Component/",
        "/Page/",
    )
    bypasses = [
        edge for edge in dependency_edges(root)
        if edge["sourceModule"]
        and edge["targetModule"]
        and edge["sourceModule"] != edge["targetModule"]
        and any(segment in edge["import"] for segment in internal_segments)
    ]
    bypass_records = [
        {
            "candidateId": f"surface-bypass-{index:05d}",
            "status": "candidate",
            "type": "INTERNAL_API_BYPASS",
            "source": edge["source"],
            "sourceModule": edge["sourceModule"],
            "import": edge["import"],
            "targetModule": edge["targetModule"],
            "confidence": "medium",
            "analyzerVersion": ANALYZER_VERSION,
        }
        for index, edge in enumerate(bypasses, 1)
    ]
    write_json(output, "public-surface", artifact(
        "public-surface",
        records + bypass_records,
    ))


def rule_references(root: Path, output: Path) -> None:
    records = []
    patterns = [
        (
            "HARDCODED_RULE_REFERENCE",
            re.compile(
                r"""(?:rule[-_][A-Za-z0-9-]+|(?:rule|characteristic|ability|item|spell|source|state|resource|damage[_-]type|domain|mechanic|action)
                (?:Code|Id|_code|_id)\s*[:=]\s*['"][^'"]+['"])""",
                re.IGNORECASE | re.VERBOSE,
            ),
        ),
        (
            "HARDCODED_RULE_NAME",
            re.compile(
                r"""(?:ruleName|rule_name|rule\.name|rule\[['"]name['"]\])
                \s*[:=]\s*['"][^'"]+['"]""",
                re.IGNORECASE | re.VERBOSE,
            ),
        ),
        (
            "RULE_CONSTANT_REFERENCE",
            re.compile(r"\b(?:RuleCode|RULE_CODES?|RuleId|RULE_IDS?)::[A-Z0-9_]+\b"),
        ),
    ]
    for path in iter_files(root):
        if classify(path) in {"documentation", "configuration"}:
            continue
        for number, line in enumerate(read_text(path).splitlines(), 1):
            for finding_type, pattern in patterns:
                if pattern.search(line):
                    records.append(evidence(
                        f"rule-ref-{len(records) + 1:05d}",
                        finding_type,
                        relative_path(root, path),
                        number,
                        "Possible concrete rule reference",
                        line,
                    ))
    write_json(output, "rule-references", artifact("rule-references", records))


def rule_matrix(root: Path, output: Path) -> None:
    files = [
        (relative_path(root, path), read_text(path))
        for path in iter_files(root)
        if path.suffix.lower() in {".ts", ".tsx", ".vue", ".js", ".jsx", ".php"}
    ]
    type_path = root / "draft-front_1.2ds/src/modules/Roleplay/Rule/Enum/RuleType.ts"
    type_text = read_text(type_path) if type_path.exists() else ""
    rule_types = sorted(set(re.findall(r"\|\s*['\"]([^'\"]+)['\"]", type_text)))
    surfaces = {
        "dto": lambda path: "/Dto/" in f"/{path}",
        "editor": lambda path: "/Editors/" in f"/{path}" or "Editor" in Path(path).stem,
        "view": lambda path: "/Cards/" in f"/{path}" or "View" in Path(path).stem or "Detail" in Path(path).stem,
        "runtime": lambda path: "/Service/" in f"/{path}" or "/Utils/" in f"/{path}",
        "tests": lambda path: classify(Path(path)) == "test",
        "mock": lambda path: "/Mock/" in f"/{path}",
    }
    records = []
    for rule_type in rule_types:
        token = re.compile(
            rf"""(?:['"]{re.escape(rule_type)}['"]|\b{re.escape(rule_type)}\b)""",
            re.IGNORECASE,
        )
        matching_paths = {
            kind: sorted(path for path, text in files if predicate(path) and token.search(text))
            for kind, predicate in surfaces.items()
        }
        fields = set()
        field_paths = []
        normalized_type = re.sub(r"[^a-z0-9]", "", rule_type.lower())
        for path, text in files:
            normalized_stem = re.sub(r"[^a-z0-9]", "", Path(path).stem.lower())
            type_specific_path = normalized_type in normalized_stem
            typed_object = re.search(
                rf"\btype\s*:\s*['\"]{re.escape(rule_type)}['\"]",
                text,
                re.IGNORECASE,
            )
            if not surfaces["dto"](path) or not (type_specific_path or typed_object):
                continue
            field_paths.append(path)
            fields.update(
                match.group(1)
                for match in re.finditer(r"^\s*([A-Za-z_][\w]*)\??\s*[:=]", text, re.MULTILINE)
            )
        records.append({
            "candidateId": f"rule-matrix-{len(records) + 1:05d}",
            "status": "candidate",
            "type": "RULE_TYPE_MATRIX",
            "ruleType": rule_type,
            "fields": sorted(fields),
            "fieldEvidencePaths": sorted(field_paths),
            "surfaces": matching_paths,
            "supportSignals": {
                kind: bool(paths)
                for kind, paths in matching_paths.items()
            },
            "confidence": "low",
            "message": "Heuristic RuleType → field → DTO/editor/view/runtime/tests matrix; requires manual semantic review.",
            "analyzerVersion": ANALYZER_VERSION,
        })
    write_json(output, "rule-matrix", artifact("rule-matrix", records))


def normalized_code_lines(text: str) -> dict[str, list[int]]:
    result: dict[str, list[int]] = defaultdict(list)
    for number, line in enumerate(text.splitlines(), 1):
        normalized = re.sub(r"\s+", " ", line.strip())
        normalized = re.sub(r"//.*$", "", normalized).strip()
        if len(normalized) >= 50 and not normalized.startswith(("*", "#")):
            result[normalized].append(number)
    return result


def method_signatures(text: str) -> dict[str, list[int]]:
    result: dict[str, list[int]] = defaultdict(list)
    pattern = re.compile(
        r"^\s*(?:(?:export|default|public|private|protected|static|async|final|abstract)\s+)*"
        r"(?:function\s+)?([A-Za-z_$][\w$]*)\s*\(([^)]*)\)"
    )
    ignored = {"if", "for", "foreach", "while", "switch", "catch"}
    for number, line in enumerate(text.splitlines(), 1):
        match = pattern.search(line)
        if not match or match.group(1) in ignored:
            continue
        parameter_count = 0 if not match.group(2).strip() else len(match.group(2).split(","))
        result[f"{match.group(1)}({parameter_count})"].append(number)
    return result


DOMAIN_KEYWORDS = (
    "modifier",
    "advantage",
    "disadvantage",
    "aggregate",
    "formula",
    "damage",
    "resistance",
    "penetration",
    "resource",
    "permission",
    "validation",
    "overlay",
    "session",
    "grant",
    "cost",
    "state",
    "dot",
    "injury",
)


def duplication(root: Path, output: Path) -> None:
    occurrences: dict[str, list[tuple[str, int]]] = defaultdict(list)
    signatures: dict[str, list[tuple[str, int]]] = defaultdict(list)
    keyword_locations: dict[str, set[str]] = defaultdict(set)
    for path in iter_files(root):
        if path.suffix.lower() not in SOURCE_EXTENSIONS:
            continue
        relative = relative_path(root, path)
        for code_line, numbers in normalized_code_lines(read_text(path)).items():
            for number in numbers:
                occurrences[code_line].append((relative, number))
        for signature, numbers in method_signatures(read_text(path)).items():
            for number in numbers:
                signatures[signature].append((relative, number))
        lowered = read_text(path).lower()
        for keyword in DOMAIN_KEYWORDS:
            if re.search(rf"\b{re.escape(keyword)}\b", lowered):
                keyword_locations[keyword].add(relative)
    records = []
    for code_line, places in occurrences.items():
        unique_places = sorted(set(places))
        unique_files = {place[0] for place in unique_places}
        if len(unique_files) > 1:
            records.append({
                "candidateId": f"dup-candidate-{len(records) + 1:05d}",
                "recordId": f"dup-candidate-{len(records) + 1:05d}",
                "status": "candidate",
                "type": "DUPLICATE_CODE_LINE",
                "confidence": "low",
                "symbol": None,
                "line": code_line,
                "locations": [
                    {"path": path, "line": number}
                    for path, number in unique_places
                ],
                "analyzerVersion": ANALYZER_VERSION,
            })
    for signature, places in signatures.items():
        unique_places = sorted(set(places))
        unique_files = {place[0] for place in unique_places}
        if len(unique_files) > 1:
            records.append({
                "candidateId": f"signature-candidate-{len(records) + 1:05d}",
                "recordId": f"signature-candidate-{len(records) + 1:05d}",
                "status": "candidate",
                "type": "DUPLICATE_METHOD_SIGNATURE",
                "confidence": "low",
                "signature": signature,
                "locations": [
                    {"path": path, "line": number}
                    for path, number in unique_places
                ],
                "analyzerVersion": ANALYZER_VERSION,
            })
    for keyword, paths in sorted(keyword_locations.items()):
        if len(paths) > 1:
            records.append({
                "candidateId": f"domain-keyword-{len(records) + 1:05d}",
                "recordId": f"domain-keyword-{len(records) + 1:05d}",
                "status": "candidate",
                "type": "DOMAIN_LOGIC_KEYWORD_CLUSTER",
                "confidence": "low",
                "keyword": keyword,
                "paths": sorted(paths),
                "message": "Domain keyword occurs across multiple implementation files; inspect ownership and duplication.",
                "analyzerVersion": ANALYZER_VERSION,
            })
    write_json(output, "duplication", artifact("duplication", records))


def ui_inventory(root: Path, output: Path) -> None:
    records = []
    for path in iter_files(root):
        text = read_text(path)
        relative = relative_path(root, path)
        if path.name == "routes.ts":
            for number, line in enumerate(text.splitlines(), 1):
                route_match = re.search(r"""path\s*:\s*['"]([^'"]+)['"]""", line)
                if route_match:
                    records.append({
                        "candidateId": f"ui-route-{len(records) + 1:05d}",
                        "status": "candidate",
                        "type": "UI_ROUTE",
                        "path": relative,
                        "line": number,
                        "route": route_match.group(1),
                        "module": resolve_module(relative),
                        "analyzerVersion": ANALYZER_VERSION,
                    })
            continue
        if path.suffix.lower() != ".vue":
            continue
        imports = extract_imports(text)
        lowered_path = relative.lower()
        cluster = next(
            (
                value for value in (
                    "moderation",
                    "combat",
                    "popup",
                    "detail",
                    "list",
                    "sheet",
                    "editor",
                    "card",
                    "npc",
                )
                if value in lowered_path
            ),
            "other",
        )
        records.append({
            "candidateId": f"ui-view-{len(records) + 1:05d}",
            "status": "candidate",
            "type": "UI_VIEW",
            "path": relative,
            "module": resolve_module(relative),
            "cluster": cluster,
            "lines": line_count(text),
            "imports": imports,
            "readModels": sorted({
                imported for imported in imports
                if any(token in imported for token in ("/Dto/", "Model", "Overview", "Read"))
            }),
            "props": re.findall(r"\b(?:defineProps|withDefaults)\s*<[^>]*>", text),
            "emits": re.findall(r"\bdefineEmits\s*<[^>]*>", text),
            "hasTemplate": "<template" in text,
            "hasScriptSetup": "<script setup" in text,
            "hasStyle": "<style" in text,
        })
    write_json(output, "ui-inventory", artifact("ui-inventory", records))


def compliance(root: Path, output: Path) -> None:
    records = []
    patterns = [
        ("FRONTEND_RELATIVE_IMPORT", re.compile(r"""from\s+['"]\.\.?/"""), "Relative import"),
        ("FRONTEND_JSON_CLONE", re.compile(r"JSON\.parse\s*\(\s*JSON\.stringify"), "JSON clone"),
        ("FRONTEND_ANY", re.compile(r"\bas\s+any\b|\bany\s*[,;=)]"), "Possible any usage"),
        ("BACKEND_RUNTIME_EXCEPTION", re.compile(r"\b(?:RuntimeException|InvalidArgumentException)\b"), "Non-domain exception"),
        ("BACKEND_SQL_OUTSIDE_SMARTTABLE", re.compile(r"\b(?:SELECT|INSERT|UPDATE|DELETE)\b", re.IGNORECASE), "Possible SQL outside SmartTable"),
    ]
    for path in iter_files(root):
        relative = relative_path(root, path)
        text = read_text(path)
        for finding_type, pattern, message in patterns:
            if finding_type.startswith("BACKEND") and not relative.startswith("www/mifrial/"):
                continue
            if finding_type.startswith("FRONTEND") and not relative.startswith("draft-front_1.2ds/"):
                continue
            if finding_type == "BACKEND_SQL_OUTSIDE_SMARTTABLE" and "Core/SmartTable" in relative:
                continue
            for number, line in enumerate(text.splitlines(), 1):
                if pattern.search(line):
                    records.append(evidence(
                        f"compliance-{len(records) + 1:05d}",
                        finding_type,
                        relative,
                        number,
                        message,
                        line,
                    ))
    write_json(output, "compliance", artifact("compliance", records))


def report(root: Path, output: Path, inputs: list[Path]) -> None:
    records = []
    source_files = inputs or sorted(output.glob("*.json"))
    for source in source_files:
        path = source if source.is_absolute() else root / source
        if not path.exists() or path.name == "report.json":
            continue
        try:
            payload = json.loads(path.read_text(encoding="utf-8"))
        except json.JSONDecodeError:
            continue
        if isinstance(payload, dict) and isinstance(payload.get("records"), list):
            records.extend(payload["records"])
    by_key: dict[str, dict] = {}
    for record in records:
        key = json.dumps({
            "type": record.get("type"),
            "path": record.get("path"),
            "line": record.get("line"),
            "message": record.get("message"),
            "evidence": record.get("evidence"),
            "locations": record.get("locations"),
            "paths": record.get("paths"),
        }, sort_keys=True, ensure_ascii=False)
        by_key[key] = record
    ordered_records = sorted(
        by_key.values(),
        key=lambda record: (
            str(record.get("type", "")),
            str(record.get("path", "")),
            (
                record.get("line", 0)
                if isinstance(record.get("line", 0), int)
                else 0
            ),
            str(record.get("candidateId", record.get("recordId", ""))),
        ),
    )
    type_counts = Counter(str(record.get("type", "UNKNOWN")) for record in ordered_records)
    status_counts = Counter(str(record.get("status", "UNKNOWN")) for record in ordered_records)
    result = artifact("report", {
        "recordCount": len(ordered_records),
        "records": ordered_records,
        "summary": {
            "byType": dict(sorted(type_counts.items())),
            "byStatus": dict(sorted(status_counts.items())),
        },
        "sourceArtifacts": [str(path) for path in source_files],
    })
    write_json(output, "report", result)


def manifest(root: Path, output: Path) -> None:
    tracked_status = subprocess.run(
        ["git", "status", "--short"],
        cwd=root,
        check=False,
        capture_output=True,
        text=True,
    ).stdout.splitlines()
    head = subprocess.run(
        ["git", "rev-parse", "HEAD"],
        cwd=root,
        check=False,
        capture_output=True,
        text=True,
    ).stdout.strip()
    artifact_files = []
    for path in sorted(output.glob("*.json")):
        if path.name == "manifest.json":
            continue
        digest = hashlib.sha256(path.read_bytes()).hexdigest()
        artifact_files.append({
            "path": relative_path(root, path),
            "sha256": digest,
            "bytes": path.stat().st_size,
        })
    write_json(output, "manifest", artifact("manifest", {
        "head": head,
        "gitStatus": tracked_status,
        "artifacts": artifact_files,
    }))


def main() -> int:
    args = parse_args()
    root = args.root.resolve()
    output = args.output if args.output.is_absolute() else root / args.output
    commands = {
        "inventory": inventory,
        "dependencies": dependencies,
        "public-surface": public_surface,
        "rule-references": rule_references,
        "duplication": duplication,
        "rule-matrix": rule_matrix,
        "ui-inventory": ui_inventory,
        "compliance": compliance,
        "manifest": manifest,
    }
    if args.command == "report":
        report(root, output, args.input)
    else:
        commands[args.command](root, output)
    return 0


if __name__ == "__main__":
    sys.exit(main())
