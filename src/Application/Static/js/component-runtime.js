(function () {
    'use strict';

    var registry = new Map();
    var mountedRoots = new WeakMap();

    function ensureApi() {
        if (window.SemitexaComponent) {
            return window.SemitexaComponent;
        }

        window.SemitexaComponent = {
            register: register,
            scan: scan
        };

        return window.SemitexaComponent;
    }

    function register(name, mount) {
        if (typeof name !== 'string' || name.trim() === '') {
            return;
        }

        if (typeof mount !== 'function') {
            return;
        }

        registry.set(name, mount);
        scan(document);
    }

    function scan(root) {
        var scope = root instanceof Element || root instanceof Document ? root : document;
        var candidates = [];

        if (scope instanceof Element && scope.matches('[data-semitexa-component]')) {
            candidates.push(scope);
        }

        if ('querySelectorAll' in scope) {
            scope.querySelectorAll('[data-semitexa-component]').forEach(function (node) {
                candidates.push(node);
            });
        }

        candidates.forEach(function (node) {
            var name = node.getAttribute('data-semitexa-component');
            if (!name || !registry.has(name)) {
                return;
            }

            var mountedForNode = mountedRoots.get(node);
            if (!mountedForNode) {
                mountedForNode = new Set();
                mountedRoots.set(node, mountedForNode);
            }

            if (mountedForNode.has(name)) {
                return;
            }

            mountedForNode.add(name);
            registry.get(name)(node, {
                componentId: node.getAttribute('data-semitexa-component-id') || '',
                componentName: name,
                script: node.getAttribute('data-semitexa-component-script') || ''
            });
        });
    }

    function init() {
        ensureApi();
        scan(document);

        document.addEventListener('semitexa:block:rendered', function (event) {
            var root = event && event.detail && event.detail.block instanceof Element
                ? event.detail.block
                : document;
            scan(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
