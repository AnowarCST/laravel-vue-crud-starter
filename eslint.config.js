import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import prettier from 'eslint-config-prettier';
import globals from 'globals';

export default [
    {
        ignores: [
            'public/build/**',
            'public/hot',
            'vendor/**',
            'node_modules/**',
            'storage/**',
            'bootstrap/cache/**',
            // Local tool data (for example Claude Code worktrees), never part of the repository.
            '.claude/**',
        ],
    },
    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        files: ['resources/js/**/*.{js,vue}'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: { ...globals.browser },
        },
    },
    {
        files: ['vite.config.js', 'eslint.config.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: { ...globals.node },
        },
    },
    {
        // Image render script: runs in Node; the page.evaluate() callback runs in the browser.
        files: ['resources/images/**/*.mjs'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: { ...globals.node, ...globals.browser },
        },
    },
    // Must stay last: turns off every rule that would conflict with Prettier.
    prettier,
];
