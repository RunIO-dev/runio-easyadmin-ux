// Runs before EasyAdmin's page-layout.js, which reads these attributes to set the sidebar width.
(() => {
    const layout = document.body.dataset.uxLayout;
    if ('sidebar' === layout) {
        return;
    }

    document.body.dataset.eaSidebarWidth = 'compact' === layout ? 'compact' : 'normal';
    document.body.dataset.eaSidebarWidthConfigured = 'true';
})();
