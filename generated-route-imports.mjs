import path from 'node:path';

// Wayfinder regenerates these modules during builds and development.
// Restore required runtime imports before Vite evaluates generated modules.
export function repairGeneratedRouteImports(code, id) {
    const cleanId = id.split('?')[0].replace(/\\/g, '/');
    if (!/\/resources\/js\/(routes|actions)\/.*\.ts$/.test(cleanId)) return null;
    const helpers = ['queryParams', 'applyUrlDefaults', 'validateParameters'];
    const imported = new Set();
    let repaired = code.replace(/import\s+(type\s+)?\{([^}]+)\}\s+from\s+(['"])([^'"]*wayfinder)\3\s*;?/g,
        (statement, allType, names, quote, source) => {
            // Generated helpers are values, even if a formatter marked them as types.
            const parts = names.split(',').map(part => part.trim()).filter(Boolean);
            const values = [];
            const types = [];
            for (const part of parts) {
                const untyped = part.replace(/^type\s+/, '');
                const name = untyped.split(/\s+as\s+/)[0].trim();
                if (helpers.includes(name)) {
                    imported.add(untyped.split(/\s+as\s+/).pop().trim());
                    values.push(untyped);
                } else if (allType || part.startsWith('type ')) {
                    types.push(untyped);
                } else values.push(untyped);
            }
            return [values.length ? `import { ${values.join(', ')} } from ${quote}${source}${quote};` : '',
                types.length ? `import type { ${types.join(', ')} } from ${quote}${source}${quote};` : ''].filter(Boolean).join('\n');
        });
    const missing = helpers.filter(name => new RegExp(`\\b${name}\\s*(?:<[^>]*>)?\\s*\\(`).test(repaired) && !imported.has(name));
    if (missing.length) {
        const jsRoot = cleanId.slice(0, cleanId.indexOf('/resources/js/') + '/resources/js/'.length);
        let relative = path.posix.relative(path.posix.dirname(cleanId), jsRoot + 'wayfinder');
        if (!relative.startsWith('.')) relative = './' + relative;
        repaired = `import { ${missing.join(', ')} } from '${relative}';\n` + repaired;
    }
    return repaired === code ? null : { code: repaired, map: null };
}

export default function generatedRouteImports() {
    return {
        name: 'ghadams-generated-route-imports',
        enforce: 'pre',
        transform: repairGeneratedRouteImports,
    };
}
