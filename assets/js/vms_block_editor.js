(function(wp, config) {
    if (!wp || !wp.plugins || !config) {
        return;
    }

    var registerPlugin = wp.plugins.registerPlugin;
    var PluginMoreMenuItem = wp.editPost.PluginMoreMenuItem;
    var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
    var Button = wp.components.Button;
    var PanelRow = wp.components.PanelRow;
    var __ = wp.i18n.__;
    var createElement = wp.element.createElement;
    var Fragment = wp.element.Fragment;

    var openEditor = function() {
        window.open(config.editorUrl, '_blank');
    };

    registerPlugin('vms-v2-editor-buttons', {
        render: function() {
            return createElement(
                Fragment,
                null,
                createElement(
                    PluginMoreMenuItem,
                    {
                        icon: 'edit',
                        onClick: openEditor
                    },
                    config.label
                ),
                createElement(
                    PluginDocumentSettingPanel,
                    {
                        name: 'vms-v2-editor-panel',
                        title: __('Verstka Editor', 'verstka-backend-v2'),
                        className: 'vms-v2-editor-panel'
                    },
                    createElement(
                        PanelRow,
                        null,
                        createElement(
                            Button,
                            {
                                isPrimary: true,
                                onClick: openEditor
                            },
                            config.label
                        )
                    )
                )
            );
        }
    });
})(window.wp, window.vmsV2BlockEditor);
