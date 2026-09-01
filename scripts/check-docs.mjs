import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';

const root = process.cwd();
const docsRoot = join(root, 'docs');
const quadrants = ['tutorials', 'how-to', 'explanation', 'reference'];
const requiredPages = [
    'docs/index.md',
    'docs/tutorials/evaluate-communitykind.md',
    'docs/how-to/local-setup.md',
    'docs/how-to/docker-setup.md',
    'docs/how-to/operate-an-installation.md',
    'docs/how-to/contribute.md',
    'docs/explanation/domain-and-tenancy.md',
    'docs/explanation/privacy-and-safety.md',
    'docs/reference/configuration.md',
    'docs/reference/decisions.md',
];
const errors = [];

for (const page of requiredPages) {
    if (!existsSync(join(root, page))) {
        errors.push(`Missing required documentation page: ${page}`);
    }
}

const publishedPages = [join(docsRoot, 'index.md')];
for (const quadrant of quadrants) {
    const directory = join(docsRoot, quadrant);
    const pages = existsSync(directory)
        ? readdirSync(directory).filter((file) => file.endsWith('.md'))
        : [];

    if (pages.length === 0) {
        errors.push(`Documentation quadrant is empty: docs/${quadrant}`);
    }

    publishedPages.push(...pages.map((page) => join(directory, page)));
}

for (const page of publishedPages.filter(existsSync)) {
    const contents = readFileSync(page, 'utf8');
    const displayPath = relative(root, page);
    const reviewed = contents.match(/<!-- reviewed: (\d{4}-\d{2}-\d{2}) -->/);

    if (!reviewed) {
        errors.push(`${displayPath}: missing reviewed date`);
    } else {
        const reviewedAt = new Date(`${reviewed[1]}T00:00:00Z`);
        const ageDays = (Date.now() - reviewedAt.getTime()) / 86_400_000;

        if (Number.isNaN(reviewedAt.getTime()) || ageDays < -1) {
            errors.push(
                `${displayPath}: invalid or future reviewed date ${reviewed[1]}`,
            );
        } else if (ageDays > 183) {
            errors.push(
                `${displayPath}: stale review date ${reviewed[1]} (over 183 days)`,
            );
        }
    }

    for (const match of contents.matchAll(/!?\[[^\]]*\]\(([^)]+)\)/g)) {
        const target = match[1].trim();

        if (/^(?:https?:|mailto:|#)/.test(target)) {
            continue;
        }

        const path = decodeURIComponent(target.split('#')[0].split('?')[0]);
        if (!existsSync(resolve(dirname(page), path))) {
            errors.push(`${displayPath}: broken link ${target}`);
        }
    }
}

if (errors.length > 0) {
    console.error(errors.join('\n'));
    process.exit(1);
}

console.log(
    `Documentation check passed for ${publishedPages.length} published pages.`,
);
