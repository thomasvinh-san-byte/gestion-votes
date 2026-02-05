/**
 * ESLint configuration for AG-VOTE (ESLint v9 flat config format).
 */
export default [
  {
    ignores: [
      'vendor/**',
      'node_modules/**',
      'coverage-report/**',
      'storage/**'
    ]
  },
  {
    files: ['**/*.js'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        window: 'readonly',
        document: 'readonly',
        console: 'readonly',
        setTimeout: 'readonly',
        setInterval: 'readonly',
        clearTimeout: 'readonly',
        clearInterval: 'readonly',
        fetch: 'readonly',
        localStorage: 'readonly',
        sessionStorage: 'readonly',
        URLSearchParams: 'readonly',
        FormData: 'readonly',
        Request: 'readonly',
        Response: 'readonly',
        Headers: 'readonly',
        URL: 'readonly',
        Event: 'readonly',
        CustomEvent: 'readonly',
        EventSource: 'readonly',
        WebSocket: 'readonly',
        MutationObserver: 'readonly',
        IntersectionObserver: 'readonly',
        ResizeObserver: 'readonly',
        navigator: 'readonly',
        location: 'readonly',
        history: 'readonly',
        customElements: 'readonly',
        HTMLElement: 'readonly',
        // AG-VOTE globals
        htmx: 'readonly',
        MeetingContext: 'readonly',
        api: 'readonly',
        escapeHtml: 'readonly',
        setNotif: 'readonly',
        log: 'readonly',
        state: 'writable',
        Shared: 'readonly',
        PageComponents: 'readonly',
        Utils: 'readonly',
        Auth: 'readonly',
        ShellDrawer: 'readonly',
        MobileNav: 'readonly'
      }
    },
    rules: {
      'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
      'semi': ['error', 'always'],
      'quotes': ['warn', 'single', { avoidEscape: true }],
      'indent': ['warn', 2],
      'eqeqeq': ['warn', 'smart'],
      'no-console': 'off',
      'no-undef': 'error'
    }
  }
];
