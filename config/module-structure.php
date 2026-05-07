<?php

declare(strict_types=1);

use Semitexa\Dev\Application\Service\Ai\Verify\Structure\LocalModuleStructureExtension;
use Semitexa\Dev\Application\Service\Ai\Verify\Structure\ModuleStructureRule;

if (!class_exists(LocalModuleStructureExtension::class) || !class_exists(ModuleStructureRule::class)) {
    return null;
}

$pascalCasePhp = '/^[A-Z][A-Za-z0-9_]*\.php$/';

return new LocalModuleStructureExtension(
    package: 'ssr',
    topLevelDirectories: [
        'Contract',
        'Seo',
    ],
    pathRules: [
        'Contract' => new ModuleStructureRule(
            path: 'Contract',
            allowedFilePatterns: ['/^[A-Z][A-Za-z0-9_]*Interface\.php$/'],
            mode: ModuleStructureRule::MODE_LEAF_FILES_ONLY,
            rationale: 'Backward-compatibility contract aliases kept at the legacy Semitexa\\Ssr\\Contract namespace.',
        ),
        'Seo' => new ModuleStructureRule(
            path: 'Seo',
            allowedDirectories: ['Sitemap'],
            rationale: 'Backward-compatibility SEO shims kept at the legacy Semitexa\\Ssr\\Seo namespace.',
        ),
        'Seo/Sitemap' => new ModuleStructureRule(
            path: 'Seo/Sitemap',
            allowedFilePatterns: [$pascalCasePhp],
            mode: ModuleStructureRule::MODE_LEAF_FILES_ONLY,
            rationale: 'Legacy sitemap alias shims that forward to Application/Service/Seo/Sitemap.',
        ),
    ],
    reason: 'semitexa-ssr preserves legacy public namespaces for contract and sitemap symbols via alias shims outside the generic Application/Domain split.',
);
