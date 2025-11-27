/**
 * @jest-environment jsdom
 */

import '@testing-library/jest-dom';
import { screen, waitFor, fireEvent } from '@testing-library/dom';

// Mock jQuery
global.$ = global.jQuery = require('jquery');

// Mock WordPress globals
global.ffpAutomation = {
    ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
    restUrl: '/wp-json/formflow/v1/',
    nonce: 'test-nonce',
    restNonce: 'rest-nonce',
    workflowId: null,
    strings: {
        flow: 'Flow Control',
        logic: 'Logic',
        data: 'Data',
        actions: 'Actions',
        duplicate: 'Duplicate',
        delete: 'Delete',
        workflow_saved: 'Workflow saved!'
    }
};

describe('FFPAutomation Builder', () => {
    let FFPAutomation;
    let mockAjax;

    beforeEach(() => {
        // Setup DOM
        document.body.innerHTML = `
            <div id="ffp-workflow-canvas">
                <div id="ffp-workflow-nodes"></div>
                <svg id="ffp-workflow-svg"></svg>
            </div>
            <div id="ffp-node-palette"></div>
            <div id="ffp-property-panel">
                <div class="ffp-property-title"></div>
                <div class="ffp-property-content"></div>
            </div>
            <div id="ffp-context-menu"></div>
            <div id="ffp-builder-notices"></div>
            <input type="text" id="ffp-workflow-name" value="Test Workflow" />
            <input type="text" id="ffp-workflow-description" value="" />
            <select id="ffp-workflow-status"><option value="draft">Draft</option></select>
            <button id="ffp-zoom-in">+</button>
            <button id="ffp-zoom-out">-</button>
            <button id="ffp-zoom-reset">Reset</button>
            <button id="ffp-undo">Undo</button>
            <button id="ffp-redo">Redo</button>
            <button id="ffp-save-workflow">Save</button>
            <button id="ffp-test-workflow">Test</button>
            <button id="ffp-delete-selected">Delete</button>
            <button id="ffp-snap-grid">Snap to Grid</button>
            <button id="ffp-auto-layout">Auto Layout</button>
            <input type="text" id="ffp-palette-search" />
            <span id="ffp-zoom-level">100%</span>
        `;

        // Mock AJAX
        mockAjax = jest.spyOn($, 'ajax');

        // Load the automation builder module
        require('../../../src/js/automation-builder.js');
        FFPAutomation = window.FFPAutomation;
    });

    afterEach(() => {
        mockAjax.mockRestore();
        jest.clearAllMocks();
    });

    describe('Initialization', () => {
        test('should initialize with default state', () => {
            expect(FFPAutomation.state).toBeDefined();
            expect(FFPAutomation.state.nodes).toEqual([]);
            expect(FFPAutomation.state.connections).toEqual([]);
            expect(FFPAutomation.state.zoom).toBe(1);
            expect(FFPAutomation.state.snapToGrid).toBe(true);
        });

        test('should have all node types registered', () => {
            expect(FFPAutomation.nodeTypes.start).toBeDefined();
            expect(FFPAutomation.nodeTypes.end).toBeDefined();
            expect(FFPAutomation.nodeTypes.condition).toBeDefined();
            expect(FFPAutomation.nodeTypes.send_email).toBeDefined();
            expect(FFPAutomation.nodeTypes.http_request).toBeDefined();
        });

        test('should create default workflow on init', () => {
            FFPAutomation.init();

            const startNode = FFPAutomation.state.nodes.find(n => n.type === 'start');
            const endNode = FFPAutomation.state.nodes.find(n => n.type === 'end');

            expect(startNode).toBeDefined();
            expect(endNode).toBeDefined();
        });
    });

    describe('Node Management', () => {
        test('should add node to canvas', () => {
            const node = FFPAutomation.addNode('send_email', 200, 200);

            expect(node).toBeDefined();
            expect(node.type).toBe('send_email');
            expect(node.x).toBe(200);
            expect(node.y).toBe(200);
            expect(FFPAutomation.state.nodes).toContain(node);
        });

        test('should snap node to grid when enabled', () => {
            FFPAutomation.state.snapToGrid = true;
            FFPAutomation.state.gridSize = 20;

            const node = FFPAutomation.addNode('condition', 213, 227);

            expect(node.x).toBe(220); // Snapped to nearest 20
            expect(node.y).toBe(220);
        });

        test('should render node to DOM', () => {
            const node = FFPAutomation.addNode('send_email', 100, 100);

            FFPAutomation.renderNode(node);

            const $node = $(`.ffp-node[data-id="${node.id}"]`);
            expect($node.length).toBe(1);
            expect($node.find('.ffp-node-title').text()).toBe('Send Email');
        });

        test('should delete node', () => {
            const node = FFPAutomation.addNode('condition', 100, 100);
            const nodeId = node.id;

            FFPAutomation.deleteNode(nodeId);

            const found = FFPAutomation.state.nodes.find(n => n.id === nodeId);
            expect(found).toBeUndefined();
        });

        test('should not delete start node', () => {
            const startNode = FFPAutomation.state.nodes.find(n => n.type === 'start');

            FFPAutomation.deleteNode(startNode.id);

            const found = FFPAutomation.state.nodes.find(n => n.type === 'start');
            expect(found).toBeDefined();
        });

        test('should duplicate node', () => {
            const originalNode = FFPAutomation.addNode('http_request', 100, 100);
            originalNode.label = 'Custom API Call';
            originalNode.config.url = 'https://api.example.com';

            FFPAutomation.duplicateNode(originalNode.id);

            const duplicates = FFPAutomation.state.nodes.filter(n => n.type === 'http_request');
            expect(duplicates.length).toBe(2);
            expect(duplicates[1].label).toBe('Custom API Call');
            expect(duplicates[1].config.url).toBe('https://api.example.com');
        });

        test('should not duplicate start or end nodes', () => {
            const startNode = FFPAutomation.state.nodes.find(n => n.type === 'start');

            FFPAutomation.duplicateNode(startNode.id);

            const startNodes = FFPAutomation.state.nodes.filter(n => n.type === 'start');
            expect(startNodes.length).toBe(1);
        });
    });

    describe('Connection Management', () => {
        test('should add connection between nodes', () => {
            const node1 = FFPAutomation.addNode('start', 100, 100);
            const node2 = FFPAutomation.addNode('send_email', 300, 100);

            const connection = FFPAutomation.addConnection(node1.id, node2.id, 0);

            expect(connection).toBeDefined();
            expect(connection.from).toBe(node1.id);
            expect(connection.to).toBe(node2.id);
            expect(FFPAutomation.state.connections).toContain(connection);
        });

        test('should not create duplicate connections', () => {
            const node1 = FFPAutomation.addNode('start', 100, 100);
            const node2 = FFPAutomation.addNode('end', 300, 100);

            FFPAutomation.addConnection(node1.id, node2.id, 0);
            const initialCount = FFPAutomation.state.connections.length;

            // Try to add same connection again
            FFPAutomation.state.pendingConnection = { from: node1.id, outputIndex: 0 };
            FFPAutomation.endConnection(node2.id);

            expect(FFPAutomation.state.connections.length).toBe(initialCount);
        });

        test('should not connect node to itself', () => {
            const node = FFPAutomation.addNode('condition', 100, 100);

            FFPAutomation.state.pendingConnection = { from: node.id, outputIndex: 0 };
            FFPAutomation.endConnection(node.id);

            expect(FFPAutomation.state.connections.length).toBe(0);
        });

        test('should delete connection', () => {
            const node1 = FFPAutomation.addNode('start', 100, 100);
            const node2 = FFPAutomation.addNode('end', 300, 100);
            const connection = FFPAutomation.addConnection(node1.id, node2.id, 0);

            FFPAutomation.deleteConnection(connection.id);

            expect(FFPAutomation.state.connections).not.toContain(connection);
        });

        test('should create bezier curve path', () => {
            const path = FFPAutomation.createConnectionPath(100, 100, 300, 200);

            expect(path).toContain('M100,100');
            expect(path).toContain('C');
        });

        test('should update all connections when node moves', () => {
            const node1 = FFPAutomation.addNode('start', 100, 100);
            const node2 = FFPAutomation.addNode('end', 300, 100);
            const connection = FFPAutomation.addConnection(node1.id, node2.id, 0);

            // Render connection to DOM
            FFPAutomation.renderConnection(connection);

            // Move node
            node1.x = 150;
            node1.y = 150;

            FFPAutomation.updateConnections();

            // Connection should be updated
            const $line = $(`[data-id="${connection.id}"]`);
            expect($line.length).toBe(1);
        });
    });

    describe('Canvas Interaction', () => {
        test('should zoom in', () => {
            FFPAutomation.state.zoom = 1;

            $('#ffp-zoom-in').trigger('click');

            expect(FFPAutomation.state.zoom).toBeGreaterThan(1);
        });

        test('should zoom out', () => {
            FFPAutomation.state.zoom = 1;

            $('#ffp-zoom-out').trigger('click');

            expect(FFPAutomation.state.zoom).toBeLessThan(1);
        });

        test('should reset zoom and pan', () => {
            FFPAutomation.state.zoom = 1.5;
            FFPAutomation.state.pan = { x: 100, y: 100 };

            $('#ffp-zoom-reset').trigger('click');

            expect(FFPAutomation.state.zoom).toBe(1);
            expect(FFPAutomation.state.pan).toEqual({ x: 0, y: 0 });
        });

        test('should update zoom indicator', () => {
            FFPAutomation.state.zoom = 1.5;

            FFPAutomation.updateZoomIndicator();

            expect($('#ffp-zoom-level').text()).toBe('150%');
        });

        test('should toggle snap to grid', () => {
            FFPAutomation.state.snapToGrid = true;

            $('#ffp-snap-grid').trigger('click');

            expect(FFPAutomation.state.snapToGrid).toBe(false);
        });
    });

    describe('Node Selection', () => {
        test('should select node', () => {
            const node = FFPAutomation.addNode('send_email', 100, 100);
            FFPAutomation.renderNode(node);

            FFPAutomation.selectNode(node.id);

            expect(FFPAutomation.state.selectedNode).toBe(node);
            expect($(`.ffp-node[data-id="${node.id}"]`).hasClass('ffp-node-selected')).toBe(true);
        });

        test('should deselect all nodes', () => {
            const node = FFPAutomation.addNode('send_email', 100, 100);
            FFPAutomation.renderNode(node);
            FFPAutomation.selectNode(node.id);

            FFPAutomation.deselectAll();

            expect(FFPAutomation.state.selectedNode).toBeNull();
            expect($('.ffp-node-selected').length).toBe(0);
        });

        test('should select all nodes', () => {
            FFPAutomation.addNode('send_email', 100, 100);
            FFPAutomation.addNode('condition', 200, 200);

            FFPAutomation.selectAll();

            expect($('.ffp-node-selected').length).toBeGreaterThan(0);
        });
    });

    describe('Property Panel', () => {
        test('should show property panel for selected node', () => {
            const node = FFPAutomation.addNode('send_email', 100, 100);

            FFPAutomation.showPropertyPanel(node);

            expect($('#ffp-property-panel').hasClass('active')).toBe(true);
            expect($('#ffp-property-panel .ffp-property-title').text()).toBe('Send Email');
        });

        test('should render property fields for send_email node', () => {
            const node = FFPAutomation.addNode('send_email', 100, 100);
            node.config.to = 'test@example.com';

            FFPAutomation.showPropertyPanel(node);

            expect($('input[name="config.to"]').length).toBeGreaterThan(0);
            expect($('input[name="config.subject"]').length).toBeGreaterThan(0);
            expect($('textarea[name="config.body"]').length).toBeGreaterThan(0);
        });

        test('should render property fields for delay node', () => {
            const node = FFPAutomation.addNode('delay', 100, 100);

            FFPAutomation.showPropertyPanel(node);

            expect($('input[name="config.duration"]').length).toBeGreaterThan(0);
            expect($('select[name="config.unit"]').length).toBeGreaterThan(0);
        });

        test('should update node config when property changes', () => {
            const node = FFPAutomation.addNode('send_email', 100, 100);
            FFPAutomation.selectNode(node.id);
            FFPAutomation.state.selectedNode = node;

            FFPAutomation.updateNodeConfig(node.id, 'config.to', 'new@example.com');

            expect(node.config.to).toBe('new@example.com');
        });

        test('should hide property panel', () => {
            $('#ffp-property-panel').addClass('active');

            FFPAutomation.hidePropertyPanel();

            expect($('#ffp-property-panel').hasClass('active')).toBe(false);
        });
    });

    describe('Keyboard Shortcuts', () => {
        test('should delete selected node on Delete key', () => {
            const node = FFPAutomation.addNode('condition', 100, 100);
            FFPAutomation.state.selectedNode = node;

            const event = new KeyboardEvent('keydown', { key: 'Delete' });
            $(document).trigger(event);

            const found = FFPAutomation.state.nodes.find(n => n.id === node.id);
            expect(found).toBeUndefined();
        });

        test('should save on Ctrl+S', () => {
            mockAjax.mockImplementation(({ success }) => {
                success({ success: true, data: { id: 1 } });
                return Promise.resolve();
            });

            const event = new KeyboardEvent('keydown', { key: 's', ctrlKey: true });
            event.preventDefault = jest.fn();
            $(document).trigger(event);

            expect(event.preventDefault).toHaveBeenCalled();
        });

        test('should deselect on Escape', () => {
            const node = FFPAutomation.addNode('condition', 100, 100);
            FFPAutomation.selectNode(node.id);

            const event = new KeyboardEvent('keydown', { key: 'Escape' });
            $(document).trigger(event);

            expect(FFPAutomation.state.selectedNode).toBeNull();
        });
    });

    describe('Auto Layout', () => {
        test('should arrange nodes automatically', () => {
            const start = FFPAutomation.addNode('start', 10, 10);
            const email = FFPAutomation.addNode('send_email', 50, 50);
            const end = FFPAutomation.addNode('end', 90, 90);

            FFPAutomation.addConnection(start.id, email.id, 0);
            FFPAutomation.addConnection(email.id, end.id, 0);

            const originalX = email.x;
            FFPAutomation.autoLayout();

            expect(email.x).not.toBe(originalX);
        });
    });

    describe('History Management', () => {
        test('should push state to history', () => {
            const initialLength = FFPAutomation.state.history.length;

            FFPAutomation.pushHistory();

            expect(FFPAutomation.state.history.length).toBe(initialLength + 1);
        });

        test('should undo changes', () => {
            FFPAutomation.pushHistory();

            const node = FFPAutomation.addNode('send_email', 100, 100);
            FFPAutomation.pushHistory();

            FFPAutomation.undo();

            const found = FFPAutomation.state.nodes.find(n => n.id === node.id);
            expect(found).toBeUndefined();
        });

        test('should redo changes', () => {
            FFPAutomation.pushHistory();

            const node = FFPAutomation.addNode('send_email', 100, 100);
            FFPAutomation.pushHistory();

            FFPAutomation.undo();
            FFPAutomation.redo();

            const found = FFPAutomation.state.nodes.find(n => n.type === 'send_email');
            expect(found).toBeDefined();
        });

        test('should limit history size', () => {
            for (let i = 0; i < 60; i++) {
                FFPAutomation.pushHistory();
            }

            expect(FFPAutomation.state.history.length).toBeLessThanOrEqual(50);
        });
    });

    describe('Workflow Persistence', () => {
        test('should save workflow', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: { id: 123 }
                });
                return Promise.resolve();
            });

            FFPAutomation.addNode('send_email', 100, 100);

            $('#ffp-save-workflow').trigger('click');

            await waitFor(() => {
                expect(mockAjax).toHaveBeenCalledWith(
                    expect.objectContaining({
                        data: expect.objectContaining({
                            action: 'ffp_save_workflow'
                        })
                    })
                );
            });
        });

        test('should load workflow', async () => {
            const mockWorkflow = {
                workflow: {
                    name: 'Test Workflow',
                    description: 'Test Description',
                    status: 'active',
                    nodes: JSON.stringify([
                        { id: 'node-1', type: 'start', x: 100, y: 100, label: 'Start', config: {} }
                    ]),
                    connections: JSON.stringify([])
                }
            };

            mockAjax.mockResolvedValue(mockWorkflow);

            await FFPAutomation.loadWorkflow(1);

            await waitFor(() => {
                expect(FFPAutomation.state.nodes.length).toBeGreaterThan(0);
            });
        });

        test('should render all nodes and connections', () => {
            FFPAutomation.state.nodes = [
                { id: 'node-1', type: 'start', x: 100, y: 100, label: 'Start', config: {} },
                { id: 'node-2', type: 'end', x: 300, y: 100, label: 'End', config: {} }
            ];
            FFPAutomation.state.connections = [
                { id: 'conn-1', from: 'node-1', to: 'node-2', outputIndex: 0 }
            ];

            FFPAutomation.renderAll();

            expect($('.ffp-node').length).toBe(2);
        });
    });

    describe('Workflow Testing', () => {
        test('should test workflow', async () => {
            global.ffpAutomation.workflowId = 1;

            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: { status: 'passed' }
                });
                return Promise.resolve();
            });

            $('#ffp-test-workflow').trigger('click');

            await waitFor(() => {
                expect(mockAjax).toHaveBeenCalledWith(
                    expect.objectContaining({
                        data: expect.objectContaining({
                            action: 'ffp_test_workflow'
                        })
                    })
                );
            });
        });

        test('should warn if testing unsaved workflow', () => {
            global.ffpAutomation.workflowId = null;

            FFPAutomation.test();

            expect($('.ffp-notice').length).toBeGreaterThan(0);
        });
    });

    describe('Context Menu', () => {
        test('should show context menu on right-click', () => {
            const node = FFPAutomation.addNode('send_email', 100, 100);
            FFPAutomation.renderNode(node);

            FFPAutomation.showContextMenu(200, 300, 'node', node.id);

            const $menu = $('#ffp-context-menu');
            expect($menu.is(':visible')).toBe(true);
        });

        test('should handle duplicate action from context menu', () => {
            const node = FFPAutomation.addNode('http_request', 100, 100);
            FFPAutomation.contextNodeId = node.id;

            FFPAutomation.handleContextAction('duplicate');

            const duplicates = FFPAutomation.state.nodes.filter(n => n.type === 'http_request');
            expect(duplicates.length).toBe(2);
        });
    });

    describe('Node Palette', () => {
        test('should setup node palette with categories', () => {
            FFPAutomation.setupNodePalette();

            expect($('.ffp-palette-category').length).toBeGreaterThan(0);
            expect($('.ffp-palette-item').length).toBeGreaterThan(0);
        });

        test('should filter palette items on search', () => {
            FFPAutomation.setupNodePalette();

            $('#ffp-palette-search').val('email').trigger('input');

            const visibleItems = $('.ffp-palette-item:visible');
            visibleItems.each(function() {
                const label = $(this).find('.ffp-palette-label').text().toLowerCase();
                expect(label).toContain('email');
            });
        });
    });

    describe('Notifications', () => {
        test('should show success notice', () => {
            FFPAutomation.showNotice('success', 'Operation successful!');

            expect($('.ffp-notice-success').length).toBeGreaterThan(0);
        });

        test('should auto-dismiss notice', (done) => {
            jest.useFakeTimers();

            FFPAutomation.showNotice('info', 'Test message');

            jest.advanceTimersByTime(5000);

            setTimeout(() => {
                expect($('.ffp-notice').is(':visible')).toBe(false);
                jest.useRealTimers();
                done();
            }, 100);
        });
    });
});
