/**
 * FormFlow Pro - Elementor Editor Integration
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

(function ($) {
    'use strict';

    /**
     * Elementor Editor Handler
     */
    const FormFlowElementorEditor = {
        /**
         * Initialize
         */
        init() {
            // Wait for Elementor editor to be ready
            $(window).on('elementor:init', () => {
                this.onElementorReady();
            });
        },

        /**
         * Called when Elementor editor is ready
         */
        onElementorReady() {
            console.log('FormFlow Elementor Editor initialized');

            // Add custom editor behaviors
            this.addEditorBehaviors();

            // Register panel controls enhancement
            this.enhancePanelControls();
        },

        /**
         * Add custom editor behaviors
         */
        addEditorBehaviors() {
            // Listen for widget changes
            elementor.channels.editor.on('change', (view) => {
                if (view.model.get('widgetType') === 'formflow-form') {
                    this.onFormWidgetChange(view);
                }
            });

            // Listen for widget additions
            elementor.channels.editor.on('formflow:form:added', (view) => {
                console.log('FormFlow form widget added', view);
            });
        },

        /**
         * Enhance panel controls
         */
        enhancePanelControls() {
            // Add help tooltips
            elementor.hooks.addFilter(
                'controls/base/behaviors',
                (behaviors, view) => {
                    if (view.model.get('name') === 'formflow_target_form') {
                        behaviors.HelpTooltip = {
                            behaviorClass: elementor.modules.controls.BaseData,
                        };
                    }
                    return behaviors;
                }
            );
        },

        /**
         * Handle form widget changes
         */
        onFormWidgetChange(view) {
            const settings = view.model.get('settings');
            const formId = settings.get('form_id');

            if (formId) {
                // Load form preview data
                this.loadFormPreview(formId, view);
            }
        },

        /**
         * Load form preview
         */
        loadFormPreview(formId, view) {
            $.ajax({
                url: formflowElementor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'formflow_get_form_preview',
                    form_id: formId,
                    nonce: formflowElementor.nonce,
                },
                success: (response) => {
                    if (response.success) {
                        console.log('Form preview loaded:', response.data);
                        // Update widget preview if needed
                        view.renderHTML();
                    }
                },
            });
        },

        /**
         * Add custom panel tabs
         */
        addCustomPanelTabs() {
            // This can be extended to add custom tabs to the editor panel
            console.log('Custom panel tabs can be added here');
        },

        /**
         * Register custom controls
         */
        registerCustomControls() {
            // This can be extended to add custom control types
            console.log('Custom controls can be registered here');
        },
    };

    // Initialize
    FormFlowElementorEditor.init();

})(jQuery);
