import { readFileSync } from 'node:fs';

const allowedLicenses = new Set([
    '(MIT OR CC0-1.0)',
    '0BSD',
    'Apache-2.0',
    'BSD-2-Clause',
    'BSD-3-Clause',
    'BlueOak-1.0.0',
    'CC-BY-4.0',
    'CC0-1.0',
    'GPL-2.0-only',
    'GPL-3.0-only',
    'ISC',
    'MIT',
    'MPL-2.0',
]);

const composer = JSON.parse(readFileSync('composer.json', 'utf8'));
const composerLock = JSON.parse(readFileSync('composer.lock', 'utf8'));
const packageLock = JSON.parse(readFileSync('package-lock.json', 'utf8'));
const failures = [];

if (composer.license !== 'AGPL-3.0-only') {
    failures.push('composer.json must declare AGPL-3.0-only');
}

for (const dependency of composerLock.packages ?? []) {
    const licenses = dependency.license ?? [];

    if (licenses.length === 0) {
        failures.push(`${dependency.name}: unknown Composer licence`);
        continue;
    }

    for (const license of licenses) {
        if (!allowedLicenses.has(license)) {
            failures.push(
                `${dependency.name}: unapproved Composer licence ${license}`,
            );
        }
    }
}

for (const [path, dependency] of Object.entries(packageLock.packages ?? {})) {
    if (path === '' || dependency.dev === true) {
        continue;
    }

    const name = path.replace(/^node_modules\//, '');
    const license = dependency.license;

    if (!license) {
        failures.push(`${name}: unknown npm licence`);
    } else if (!allowedLicenses.has(license)) {
        failures.push(`${name}: unapproved npm licence ${license}`);
    }
}

if (failures.length > 0) {
    console.error('Dependency licence policy failed:');
    for (const failure of failures) {
        console.error(`- ${failure}`);
    }
    process.exitCode = 1;
} else {
    console.log(
        'Production dependency licences satisfy the approved baseline policy.',
    );
}
