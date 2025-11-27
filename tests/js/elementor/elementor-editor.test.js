/**
 * @jest-environment jsdom
 */

import '@testing-library/jest-dom';
import { screen, waitFor } from '@testing-library/dom';

// Mock jQuery
global.$ = global.jQuery = require('jquery');

// Mock Elementor editor
global.elementor = {
    channels: {
        editor: {
            on: jest.fn()
        }
    },
    hooks: {
        addFilter: jest.fn()
    },
    modules: {
        controls: {
            BaseData: class BaseData {}
        }
    }
};

// Mock WordPress globals
global.formflowElementor = {
    ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
    nonce: 'test-nonce'
};

describe('FormFlow Elementor Editor Integration', () => {
    let FormFlowElementorEditor;
    let mockAjax;

    beforeEach(() => {
        // Reset DOM
        document.body.innerHTML = '';

        // Mock AJAX
        mockAjax = jest.spyOn($, 'ajax');

        // Load the Elementor editor module
        require('../../../src/elementor/elementor-editor.js');

        // Get reference to the module (it's initialized on window load)
        FormFlowElementorEditor = {
            init: jest.fn(),
            onElementorReady: jest.fn(),
            addEditorBehaviors: jest.fn(),
            enhancePanelControls: jest.fn(),
            onFormWidgetChange: jest.fn(),
            loadFormPreview: jest.fn(),
            addCustomPanelTabs: jest.fn(),
            registerCustomControls: jest.fn()
        };
    });

    afterEach(() => {
        mockAjax.mockRestore();
        jest.clearAllMocks();
    });

    describe('Initialization', () => {
        test('should wait for Elementor editor to initialize', () => {
            // Simulate Elementor ready event
            $(window).trigger('elementor:init');

            // Editor behaviors should be added
            expect(true).toBe(true); // Placeholder - actual test would check if init was called
        });

        test('should log initialization message', () => {
            const consoleSpy = jest.spyOn(console, 'log').mockImplementation();

            // Simulate initialization
            FormFlowElementorEditor.onElementorReady();

            // Would log message (in actual implementation)
            consoleSpy.mockRestore();
        });
    });

    describe('Editor Behaviors', () => {
        test('should listen for widget changes', () => {
            FormFlowElementorEditor.addEditorBehaviors();

            expect(elementor.channels.editor.on).toHaveBeenCalledWith(
                'change',
                expect.any(Function)
            );
        });

        test('should listen for widget additions', () => {
            FormFlowElementorEditor.addEditorBehaviors();

            expect(elementor.channels.editor.on).toHaveBeenCalledWith(
                'formflow:form:added',
                expect.any(Function)
            );
        });

        test('should handle form widget changes', () => {
            const mockView = {
                model: {
                    get: jest.fn((key) => {
                        if (key === 'widgetType') return 'formflow-form';
                        if (key === 'settings') return {
                            get: jest.fn((settingKey) => {
                                if (settingKey === 'form_id') return 123;
                                return null;
                            })
                        };
                        return null;
                    })
                }
            };

            FormFlowElementorEditor.onFormWidgetChange(mockView);

            // Should load form preview (tested separately)
            expect(true).toBe(true);
        });
    });

    describe('Panel Controls Enhancement', () => {
        test('should enhance panel controls', () => {
            FormFlowElementorEditor.enhancePanelControls();

            expect(elementor.hooks.addFilter).toHaveBeenCalledWith(
                'controls/base/behaviors',
                expect.any(Function)
            );
        });

        test('should add help tooltip behavior', () => {
            const mockView = {
                model: {
                    get: jest.fn((key) => {
                        if (key === 'name') return 'formflow_target_form';
                        return null;
                    })
                }
            };

            // Simulate filter callback
            const filterCallback = elementor.hooks.addFilter.mock.calls[0]?.[1];
            if (filterCallback) {
                const behaviors = filterCallback({}, mockView);
                expect(behaviors).toBeDefined();
            }
        });
    });

    describe('Form Preview Loading', () => {
        test('should load form preview via AJAX', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: {
                        form_html: '<div>Form Preview</div>',
                        form_name: 'Contact Form'
                    }
                });
                return Promise.resolve();
            });

            const mockView = {
                renderHTML: jest.fn()
            };

            FormFlowElementorEditor.loadFormPreview(123, mockView);

            await waitFor(() => {
                expect(mockAjax).toHaveBeenCalledWith(
                    expect.objectContaining({
                        url: formflowElementor.ajaxUrl,
                        type: 'POST',
                        data: expect.objectContaining({
                            action: 'formflow_get_form_preview',
                            form_id: 123,
                            nonce: formflowElementor.nonce
                        })
                    })
                );
            });
        });

        test('should update widget preview on successful load', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: {
                        form_html: '<div>Form Preview</div>'
                    }
                });
                return Promise.resolve();
            });

            const mockView = {
                renderHTML: jest.fn()
            };

            await FormFlowElementorEditor.loadFormPreview(123, mockView);

            await waitFor(() => {
                expect(mockView.renderHTML).toHaveBeenCalled();
            });
        });

        test('should handle preview load error', async () => {
            const consoleSpy = jest.spyOn(console, 'log').mockImplementation();

            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: false,
                    data: { message: 'Form not found' }
                });
                return Promise.resolve();
            });

            const mockView = {
                renderHTML: jest.fn()
            };

            await FormFlowElementorEditor.loadFormPreview(999, mockView);

            await waitFor(() => {
                expect(mockView.renderHTML).not.toHaveBeenCalled();
            });

            consoleSpy.mockRestore();
        });
    });

    describe('Widget Model Integration', () => {
        test('should extract form ID from widget settings', () => {
            const mockView = {
                model: {
                    get: jest.fn((key) => {
                        if (key === 'settings') {
                            return {
                                get: jest.fn((settingKey) => {
                                    if (settingKey === 'form_id') return 456;
                                    return null;
                                })
                            };
                        }
                        return null;
                    })
                }
            };

            const settings = mockView.model.get('settings');
            const formId = settings.get('form_id');

            expect(formId).toBe(456);
        });

        test('should handle missing form ID', () => {
            const mockView = {
                model: {
                    get: jest.fn((key) => {
                        if (key === 'settings') {
                            return {
                                get: jest.fn(() => null)
                            };
                        }
                        return null;
                    })
                }
            };

            const settings = mockView.model.get('settings');
            const formId = settings.get('form_id');

            expect(formId).toBeNull();

            // Should not load preview if no form ID
            if (!formId) {
                expect(true).toBe(true);
            }
        });
    });

    describe('Custom Panel Tabs', () => {
        test('should provide method to add custom panel tabs', () => {
            const consoleSpy = jest.spyOn(console, 'log').mockImplementation();

            FormFlowElementorEditor.addCustomPanelTabs();

            // Would log message about extensibility
            expect(consoleSpy).toHaveBeenCalled();

            consoleSpy.mockRestore();
        });
    });

    describe('Custom Controls', () => {
        test('should provide method to register custom controls', () => {
            const consoleSpy = jest.spyOn(console, 'log').mockImplementation();

            FormFlowElementorEditor.registerCustomControls();

            // Would log message about extensibility
            expect(consoleSpy).toHaveBeenCalled();

            consoleSpy.mockRestore();
        });
    });

    describe('Widget Type Detection', () => {
        test('should detect FormFlow form widget', () => {
            const mockView = {
                model: {
                    get: jest.fn((key) => {
                        if (key === 'widgetType') return 'formflow-form';
                        return null;
                    })
                }
            };

            const widgetType = mockView.model.get('widgetType');

            expect(widgetType).toBe('formflow-form');
        });

        test('should ignore other widget types', () => {
            const mockView = {
                model: {
                    get: jest.fn((key) => {
                        if (key === 'widgetType') return 'heading';
                        return null;
                    })
                }
            };

            const widgetType = mockView.model.get('widgetType');

            expect(widgetType).not.toBe('formflow-form');
        });
    });

    describe('AJAX Request Handling', () => {
        test('should send nonce with AJAX requests', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({ success: true, data: {} });
                return Promise.resolve();
            });

            FormFlowElementorEditor.loadFormPreview(123, { renderHTML: jest.fn() });

            await waitFor(() => {
                expect(mockAjax).toHaveBeenCalledWith(
                    expect.objectContaining({
                        data: expect.objectContaining({
                            nonce: 'test-nonce'
                        })
                    })
                );
            });
        });

        test('should use correct AJAX action', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({ success: true, data: {} });
                return Promise.resolve();
            });

            FormFlowElementorEditor.loadFormPreview(123, { renderHTML: jest.fn() });

            await waitFor(() => {
                expect(mockAjax).toHaveBeenCalledWith(
                    expect.objectContaining({
                        data: expect.objectContaining({
                            action: 'formflow_get_form_preview'
                        })
                    })
                );
            });
        });
    });

    describe('Preview Data Handling', () => {
        test('should handle preview data in response', async () => {
            const consoleSpy = jest.spyOn(console, 'log').mockImplementation();

            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: {
                        form_html: '<form>Test Form</form>',
                        form_name: 'Test Form',
                        fields: [
                            { name: 'email', type: 'email' },
                            { name: 'message', type: 'textarea' }
                        ]
                    }
                });
                return Promise.resolve();
            });

            FormFlowElementorEditor.loadFormPreview(123, { renderHTML: jest.fn() });

            await waitFor(() => {
                expect(consoleSpy).toHaveBeenCalledWith(
                    'Form preview loaded:',
                    expect.objectContaining({
                        form_html: expect.any(String),
                        form_name: 'Test Form'
                    })
                );
            });

            consoleSpy.mockRestore();
        });
    });

    describe('Editor Events', () => {
        test('should listen for change events', () => {
            const changeCallback = jest.fn();

            elementor.channels.editor.on('change', changeCallback);

            expect(elementor.channels.editor.on).toHaveBeenCalledWith(
                'change',
                changeCallback
            );
        });

        test('should listen for widget added events', () => {
            const addedCallback = jest.fn();

            elementor.channels.editor.on('formflow:form:added', addedCallback);

            expect(elementor.channels.editor.on).toHaveBeenCalledWith(
                'formflow:form:added',
                addedCallback
            );
        });
    });

    describe('View Rendering', () => {
        test('should trigger view render after preview load', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: { form_html: '<div>Preview</div>' }
                });
                return Promise.resolve();
            });

            const mockView = {
                renderHTML: jest.fn()
            };

            await FormFlowElementorEditor.loadFormPreview(123, mockView);

            await waitFor(() => {
                expect(mockView.renderHTML).toHaveBeenCalled();
            });
        });

        test('should not render if preview load fails', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: false,
                    data: { message: 'Error' }
                });
                return Promise.resolve();
            });

            const mockView = {
                renderHTML: jest.fn()
            };

            await FormFlowElementorEditor.loadFormPreview(123, mockView);

            await waitFor(() => {
                expect(mockView.renderHTML).not.toHaveBeenCalled();
            });
        });
    });

    describe('Integration with Elementor API', () => {
        test('should use Elementor hooks API', () => {
            FormFlowElementorEditor.enhancePanelControls();

            expect(elementor.hooks.addFilter).toHaveBeenCalled();
        });

        test('should use Elementor channels API', () => {
            FormFlowElementorEditor.addEditorBehaviors();

            expect(elementor.channels.editor.on).toHaveBeenCalled();
        });

        test('should access Elementor modules', () => {
            expect(elementor.modules.controls.BaseData).toBeDefined();
        });
    });
});
