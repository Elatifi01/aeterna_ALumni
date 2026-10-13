// Remove every comment from the hand-written TypeScript and TSX sources.
//
//   node scripts/strip-ts-comments.mjs
//
// Generated code (resources/js/actions, resources/js/routes, wayfinder) is left
// alone, and so are triple-slash directives, which the compiler reads.
import fs from 'node:fs';
import path from 'node:path';
import ts from 'typescript';

const root = path.resolve(
    path.dirname(
        new URL(import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1'),
    ),
    '..',
);
const skip = [
    'resources/js/actions',
    'resources/js/routes',
    'resources/js/wayfinder',
];

function* walk(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        const rel = path.relative(root, full).replace(/\\/g, '/');
        if (skip.some((s) => rel.startsWith(s))) continue;
        if (entry.isDirectory()) yield* walk(full);
        else if (/\.(ts|tsx)$/.test(entry.name)) yield full;
    }
}

const files = [
    ...walk(path.join(root, 'resources/js')),
    path.join(root, 'vite.config.ts'),
];
let changed = 0;

for (const file of files) {
    const src = fs.readFileSync(file, 'utf8');
    const kind = file.endsWith('.tsx') ? ts.ScriptKind.TSX : ts.ScriptKind.TS;
    const sf = ts.createSourceFile(
        file,
        src,
        ts.ScriptTarget.Latest,
        true,
        kind,
    );

    const ranges = new Map(); // start -> end

    const addComments = (pos) => {
        for (const r of [
            ...(ts.getLeadingCommentRanges(src, pos) ?? []),
            ...(ts.getTrailingCommentRanges(src, pos) ?? []),
        ]) {
            const text = src.slice(r.pos, r.end);
            if (text.startsWith('///')) continue; // compiler directive
            ranges.set(r.pos, r.end);
        }
    };

    const visit = (node) => {
        addComments(node.getFullStart());
        addComments(node.getEnd());
        // `{/* … */}` in JSX: an expression container holding nothing but a
        // comment. Remove the whole container.
        if (ts.isJsxExpression(node) && node.expression === undefined) {
            ranges.set(node.getStart(), node.getEnd());
        }
        ts.forEachChild(node, visit);
    };
    visit(sf);
    // Comments after the last token.
    addComments(sf.endOfFileToken.getFullStart());

    if (ranges.size === 0) continue;

    // Widen each range to whole lines when the comment stands alone on them.
    const cuts = [...ranges.entries()]
        .map(([start, end]) => {
            const lineStart = src.lastIndexOf('\n', start - 1) + 1;
            const lineEndIdx = src.indexOf('\n', end);
            const lineEnd = lineEndIdx === -1 ? src.length : lineEndIdx;
            const before = src.slice(lineStart, start);
            const after = src.slice(end, lineEnd);
            if (before.trim() === '' && after.trim() === '') {
                return [
                    lineStart,
                    lineEnd + 1 > src.length ? src.length : lineEnd + 1,
                ];
            }
            // Trailing comment on a code line: drop it and the spaces before it.
            return [start - (before.length - before.trimEnd().length), end];
        })
        .sort((a, b) => a[0] - b[0]);

    // Merge overlaps, then apply from the end.
    const merged = [];
    for (const c of cuts) {
        const last = merged[merged.length - 1];
        if (last && c[0] <= last[1]) last[1] = Math.max(last[1], c[1]);
        else merged.push([...c]);
    }
    let out = src;
    for (const [s, e] of merged.reverse()) out = out.slice(0, s) + out.slice(e);

    // A comment line between two blank lines leaves a double blank; a comment
    // right after `{` or before `}` leaves a blank there. Tidy both.
    out = out
        .replace(/\n{3,}/g, '\n\n')
        .replace(/(\{|\(|\[|>)\n\n/g, '$1\n')
        .replace(/\n\n(\s*)(\}|\)|\])/g, '\n$1$2');

    if (out !== src) {
        fs.writeFileSync(file, out);
        changed++;
    }
}

console.log(`${changed} files changed`);
