/**
 * FormFlow Pro - Visual Automation Builder
 *
 * Drag-and-drop workflow builder with node-based visual programming
 * for creating automated form processing workflows.
 *
 * @package FormFlowPro
 * @since 3.0.0
 */

(function($) {
    'use strict';

    // Namespace
    window.FFPAutomation = window.FFPAutomation || {};

    // Builder state
    FFPAutomation.state = {
        workflow: null,
        nodes: [],
        connections: [],
        selectedNode: null,
        isDragging: false,
        isPanning: false,
        zoom: 1,
        pan: { x: 0, y: 0 },
        gridSize: 20,
        snapToGrid: true,
        history: [],
        historyIndex: -1
    };

    // Node types registry
    FFPAutomation.nodeTypes = {
        start: {
            type: 'start',
            label: 'Start',
            icon: 'flag',
            color: '#00a32a',
            inputs: 0,
            outputs: 1,
            category: 'flow'
        },
        end: {
            type: 'end',
            label: 'End',
            icon: 'yes-alt',
            color: '#d63638',
            inputs: 1,
            outputs: 0,
            category: 'flow'
        },
        condition: {
            type: 'condition',
            label: 'Condition',
            icon: 'randomize',
            color: '#dba617',
            inputs: 1,
            outputs: 2,
            category: 'logic',
            config: {
                conditions: []
            }
        },
        loop: {
            type: 'loop',
            label: 'Loop',
            icon: 'controls-repeat',
            color: '#9b59b6',
            inputs: 1,
            outputs: 2,
            category: 'logic',
            config: {
                collection: '',
                variable: 'item'
            }
        },
        delay: {
            type: 'delay',
            label: 'Delay',
            icon: 'clock',
            color: '#646970',
            inputs: 1,
            outputs: 1,
            category: 'logic',
            config: {
                duration: 60,
                unit: 'seconds'
            }
        },
        set_variable: {
            type: 'set_variable',
            label: 'Set Variable',
            icon: 'admin-generic',
            color: '#3498db',
            inputs: 1,
            outputs: 1,
            category: 'data',
            config: {
                variables: []
            }
        },
        transform: {
            type: 'transform',
            label: 'Transform Data',
            icon: 'editor-code',
            color: '#1abc9c',
            inputs: 1,
            outputs: 1,
            category: 'data',
            config: {
                transformations: []
            }
        },
        send_email: {
            type: 'send_email',
            label: 'Send Email',
            icon: 'email',
            color: '#2271b1',
            inputs: 1,
            outputs: 1,
            category: 'actions',
            config: {
                to: '',
                subject: '',
                body: '',
                template: ''
            }
        },
        send_sms: {
            type: 'send_sms',
            label: 'Send SMS',
            icon: 'smartphone',
            color: '#00a32a',
            inputs: 1,
            outputs: 1,
            category: 'actions',
            config: {
                to: '',
                message: ''
            }
        },
        http_request: {
            type: 'http_request',
            label: 'HTTP Request',
            icon: 'admin-site',
            color: '#e74c3c',
            inputs: 1,
            outputs: 1,
            category: 'actions',
            config: {
                url: '',
                method: 'POST',
                headers: {},
                body: ''
            }
        },
        database_query: {
            type: 'database_query',
            label: 'Database Query',
            icon: 'database',
            color: '#8e44ad',
            inputs: 1,
            outputs: 1,
            category: 'actions',
            config: {
                operation: 'select',
                table: '',
                conditions: []
            }
        },
        create_pdf: {
            type: 'create_pdf',
            label: 'Create PDF',
            icon: 'media-document',
            color: '#c0392b',
            inputs: 1,
            outputs: 1,
            category: 'actions',
            config: {
                template: '',
                filename: ''
            }
        },
        send_signature: {
            type: 'send_signature',
            label: 'Send for Signature',
            icon: 'edit',
            color: '#27ae60',
            inputs: 1,
            outputs: 1,
            category: 'actions',
            config: {
                signers: [],
                document: ''
            }
        }
    };

    /**
     * Initialize the automation builder
     */
    FFPAutomation.init = function() {
        FFPAutomation.canvas = document.getElementById('ffp-workflow-canvas');
        FFPAutomation.svg = document.getElementById('ffp-workflow-svg');

        if (!FFPAutomation.canvas) {
            return;
        }

        FFPAutomation.setupCanvas();
        FFPAutomation.setupToolbar();
        FFPAutomation.setupNodePalette();
        FFPAutomation.setupPropertyPanel();
        FFPAutomation.setupKeyboardShortcuts();
        FFPAutomation.setupContextMenu();

        // Load workflow if editing
        if (ffpAutomation.workflowId) {
            FFPAutomation.loadWorkflow(ffpAutomation.workflowId);
        } else {
            FFPAutomation.createDefaultWorkflow();
        }
    };

    /**
     * Setup canvas interactions
     */
    FFPAutomation.setupCanvas = function() {
        var canvas = $(FFPAutomation.canvas);

        // Pan on middle mouse drag
        canvas.on('mousedown', function(e) {
            if (e.button === 1 || (e.button === 0 && e.shiftKey)) {
                e.preventDefault();
                FFPAutomation.state.isPanning = true;
                FFPAutomation.state.panStart = { x: e.clientX, y: e.clientY };
                canvas.addClass('ffp-panning');
            }
        });

        $(document).on('mousemove', function(e) {
            if (FFPAutomation.state.isPanning) {
                var dx = e.clientX - FFPAutomation.state.panStart.x;
                var dy = e.clientY - FFPAutomation.state.panStart.y;
                FFPAutomation.state.pan.x += dx;
                FFPAutomation.state.pan.y += dy;
                FFPAutomation.state.panStart = { x: e.clientX, y: e.clientY };
                FFPAutomation.updateTransform();
            }
        });

        $(document).on('mouseup', function() {
            if (FFPAutomation.state.isPanning) {
                FFPAutomation.state.isPanning = false;
                canvas.removeClass('ffp-panning');
            }
        });

        // Zoom with scroll
        canvas.on('wheel', function(e) {
            e.preventDefault();
            var delta = e.originalEvent.deltaY > 0 ? 0.9 : 1.1;
            var newZoom = Math.max(0.25, Math.min(2, FFPAutomation.state.zoom * delta));

            // Zoom towards cursor
            var rect = canvas[0].getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;

            FFPAutomation.state.pan.x = x - (x - FFPAutomation.state.pan.x) * (newZoom / FFPAutomation.state.zoom);
            FFPAutomation.state.pan.y = y - (y - FFPAutomation.state.pan.y) * (newZoom / FFPAutomation.state.zoom);
            FFPAutomation.state.zoom = newZoom;

            FFPAutomation.updateTransform();
            FFPAutomation.updateZoomIndicator();
        });

        // Deselect on canvas click
        canvas.on('click', function(e) {
            if (e.target === this || e.target === FFPAutomation.svg) {
                FFPAutomation.deselectAll();
            }
        });

        // Setup drop zone for nodes
        canvas.on('dragover', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'copy';
        });

        canvas.on('drop', function(e) {
            e.preventDefault();
            var nodeType = e.originalEvent.dataTransfer.getData('nodeType');
            if (nodeType) {
                var rect = canvas[0].getBoundingClientRect();
                var x = (e.clientX - rect.left - FFPAutomation.state.pan.x) / FFPAutomation.state.zoom;
                var y = (e.clientY - rect.top - FFPAutomation.state.pan.y) / FFPAutomation.state.zoom;
                FFPAutomation.addNode(nodeType, x, y);
            }
        });
    };

    /**
     * Update canvas transform
     */
    FFPAutomation.updateTransform = function() {
        var transform = 'translate(' + FFPAutomation.state.pan.x + 'px, ' + FFPAutomation.state.pan.y + 'px) scale(' + FFPAutomation.state.zoom + ')';
        $('#ffp-workflow-nodes').css('transform', transform);
        $(FFPAutomation.svg).css('transform', transform);
    };

    /**
     * Update zoom indicator
     */
    FFPAutomation.updateZoomIndicator = function() {
        var percent = Math.round(FFPAutomation.state.zoom * 100);
        $('#ffp-zoom-level').text(percent + '%');
    };

    /**
     * Setup toolbar buttons
     */
    FFPAutomation.setupToolbar = function() {
        // Zoom controls
        $('#ffp-zoom-in').on('click', function() {
            FFPAutomation.state.zoom = Math.min(2, FFPAutomation.state.zoom * 1.2);
            FFPAutomation.updateTransform();
            FFPAutomation.updateZoomIndicator();
        });

        $('#ffp-zoom-out').on('click', function() {
            FFPAutomation.state.zoom = Math.max(0.25, FFPAutomation.state.zoom * 0.8);
            FFPAutomation.updateTransform();
            FFPAutomation.updateZoomIndicator();
        });

        $('#ffp-zoom-reset').on('click', function() {
            FFPAutomation.state.zoom = 1;
            FFPAutomation.state.pan = { x: 0, y: 0 };
            FFPAutomation.updateTransform();
            FFPAutomation.updateZoomIndicator();
        });

        // Undo/Redo
        $('#ffp-undo').on('click', function() {
            FFPAutomation.undo();
        });

        $('#ffp-redo').on('click', function() {
            FFPAutomation.redo();
        });

        // Save
        $('#ffp-save-workflow').on('click', function() {
            FFPAutomation.save();
        });

        // Test
        $('#ffp-test-workflow').on('click', function() {
            FFPAutomation.test();
        });

        // Delete selected
        $('#ffp-delete-selected').on('click', function() {
            FFPAutomation.deleteSelected();
        });

        // Snap to grid toggle
        $('#ffp-snap-grid').on('click', function() {
            FFPAutomation.state.snapToGrid = !FFPAutomation.state.snapToGrid;
            $(this).toggleClass('active', FFPAutomation.state.snapToGrid);
        });

        // Auto layout
        $('#ffp-auto-layout').on('click', function() {
            FFPAutomation.autoLayout();
        });
    };

    /**
     * Setup node palette (sidebar)
     */
    FFPAutomation.setupNodePalette = function() {
        var $palette = $('#ffp-node-palette');
        var categories = {};

        // Group nodes by category
        Object.keys(FFPAutomation.nodeTypes).forEach(function(type) {
            var node = FFPAutomation.nodeTypes[type];
            if (!categories[node.category]) {
                categories[node.category] = [];
            }
            categories[node.category].push({ type: type, ...node });
        });

        // Category labels
        var categoryLabels = {
            flow: ffpAutomation.strings.flow || 'Flow Control',
            logic: ffpAutomation.strings.logic || 'Logic',
            data: ffpAutomation.strings.data || 'Data',
            actions: ffpAutomation.strings.actions || 'Actions'
        };

        // Render palette
        Object.keys(categories).forEach(function(category) {
            var $category = $('<div class="ffp-palette-category">' +
                '<h4 class="ffp-palette-category-title">' + categoryLabels[category] + '</h4>' +
                '<div class="ffp-palette-items"></div>' +
                '</div>');

            var $items = $category.find('.ffp-palette-items');

            categories[category].forEach(function(node) {
                var $item = $('<div class="ffp-palette-item" draggable="true" data-type="' + node.type + '">' +
                    '<span class="ffp-palette-icon dashicons dashicons-' + node.icon + '" style="color:' + node.color + '"></span>' +
                    '<span class="ffp-palette-label">' + node.label + '</span>' +
                    '</div>');

                $item.on('dragstart', function(e) {
                    e.originalEvent.dataTransfer.setData('nodeType', node.type);
                    e.originalEvent.dataTransfer.effectAllowed = 'copy';
                });

                $items.append($item);
            });

            $palette.append($category);
        });

        // Search filter
        $('#ffp-palette-search').on('input', function() {
            var query = $(this).val().toLowerCase();
            $('.ffp-palette-item').each(function() {
                var label = $(this).find('.ffp-palette-label').text().toLowerCase();
                $(this).toggle(label.includes(query));
            });
        });
    };

    /**
     * Setup property panel
     */
    FFPAutomation.setupPropertyPanel = function() {
        // Panel is populated when a node is selected
        $('#ffp-property-panel').on('change', 'input, select, textarea', function() {
            var node = FFPAutomation.state.selectedNode;
            if (!node) return;

            var field = $(this).attr('name');
            var value = $(this).val();

            FFPAutomation.updateNodeConfig(node.id, field, value);
        });
    };

    /**
     * Setup keyboard shortcuts
     */
    FFPAutomation.setupKeyboardShortcuts = function() {
        $(document).on('keydown', function(e) {
            // Check if focused on input
            if ($(e.target).is('input, textarea, select')) {
                return;
            }

            var key = e.key.toLowerCase();

            // Delete
            if (key === 'delete' || key === 'backspace') {
                e.preventDefault();
                FFPAutomation.deleteSelected();
            }

            // Ctrl/Cmd shortcuts
            if (e.ctrlKey || e.metaKey) {
                switch (key) {
                    case 'z':
                        e.preventDefault();
                        if (e.shiftKey) {
                            FFPAutomation.redo();
                        } else {
                            FFPAutomation.undo();
                        }
                        break;
                    case 'y':
                        e.preventDefault();
                        FFPAutomation.redo();
                        break;
                    case 's':
                        e.preventDefault();
                        FFPAutomation.save();
                        break;
                    case 'a':
                        e.preventDefault();
                        FFPAutomation.selectAll();
                        break;
                    case 'd':
                        e.preventDefault();
                        FFPAutomation.duplicateSelected();
                        break;
                }
            }

            // Escape
            if (key === 'escape') {
                FFPAutomation.deselectAll();
                FFPAutomation.cancelConnection();
            }
        });
    };

    /**
     * Setup context menu
     */
    FFPAutomation.setupContextMenu = function() {
        var $menu = $('#ffp-context-menu');

        $(FFPAutomation.canvas).on('contextmenu', function(e) {
            e.preventDefault();

            var $target = $(e.target).closest('.ffp-node');
            if ($target.length) {
                FFPAutomation.showContextMenu(e.clientX, e.clientY, 'node', $target.data('id'));
            } else {
                FFPAutomation.showContextMenu(e.clientX, e.clientY, 'canvas');
            }
        });

        $(document).on('click', function() {
            $menu.hide();
        });

        $menu.on('click', '.ffp-context-item', function() {
            var action = $(this).data('action');
            FFPAutomation.handleContextAction(action);
            $menu.hide();
        });
    };

    /**
     * Show context menu
     */
    FFPAutomation.showContextMenu = function(x, y, type, nodeId) {
        var $menu = $('#ffp-context-menu');
        var items = [];

        if (type === 'node') {
            items = [
                { action: 'duplicate', icon: 'admin-page', label: ffpAutomation.strings.duplicate || 'Duplicate' },
                { action: 'delete', icon: 'trash', label: ffpAutomation.strings.delete || 'Delete' },
                { action: 'divider' },
                { action: 'copy', icon: 'clipboard', label: ffpAutomation.strings.copy || 'Copy' },
                { action: 'cut', icon: 'clipboard', label: ffpAutomation.strings.cut || 'Cut' }
            ];
            FFPAutomation.contextNodeId = nodeId;
        } else {
            items = [
                { action: 'paste', icon: 'clipboard', label: ffpAutomation.strings.paste || 'Paste' },
                { action: 'divider' },
                { action: 'selectAll', icon: 'editor-expand', label: ffpAutomation.strings.selectAll || 'Select All' },
                { action: 'autoLayout', icon: 'layout', label: ffpAutomation.strings.autoLayout || 'Auto Layout' }
            ];
        }

        var html = items.map(function(item) {
            if (item.action === 'divider') {
                return '<div class="ffp-context-divider"></div>';
            }
            return '<div class="ffp-context-item" data-action="' + item.action + '">' +
                '<span class="dashicons dashicons-' + item.icon + '"></span>' +
                '<span>' + item.label + '</span>' +
                '</div>';
        }).join('');

        $menu.html(html).css({ left: x, top: y }).show();
    };

    /**
     * Handle context menu action
     */
    FFPAutomation.handleContextAction = function(action) {
        switch (action) {
            case 'duplicate':
                FFPAutomation.duplicateNode(FFPAutomation.contextNodeId);
                break;
            case 'delete':
                FFPAutomation.deleteNode(FFPAutomation.contextNodeId);
                break;
            case 'copy':
                FFPAutomation.copyNode(FFPAutomation.contextNodeId);
                break;
            case 'cut':
                FFPAutomation.cutNode(FFPAutomation.contextNodeId);
                break;
            case 'paste':
                FFPAutomation.paste();
                break;
            case 'selectAll':
                FFPAutomation.selectAll();
                break;
            case 'autoLayout':
                FFPAutomation.autoLayout();
                break;
        }
    };

    /**
     * Create default workflow with start/end nodes
     */
    FFPAutomation.createDefaultWorkflow = function() {
        FFPAutomation.state.nodes = [];
        FFPAutomation.state.connections = [];

        FFPAutomation.addNode('start', 100, 100);
        FFPAutomation.addNode('end', 100, 400);

        FFPAutomation.pushHistory();
    };

    /**
     * Add a new node
     */
    FFPAutomation.addNode = function(type, x, y) {
        var nodeType = FFPAutomation.nodeTypes[type];
        if (!nodeType) return null;

        // Snap to grid
        if (FFPAutomation.state.snapToGrid) {
            x = Math.round(x / FFPAutomation.state.gridSize) * FFPAutomation.state.gridSize;
            y = Math.round(y / FFPAutomation.state.gridSize) * FFPAutomation.state.gridSize;
        }

        var node = {
            id: 'node-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9),
            type: type,
            label: nodeType.label,
            x: x,
            y: y,
            config: JSON.parse(JSON.stringify(nodeType.config || {}))
        };

        FFPAutomation.state.nodes.push(node);
        FFPAutomation.renderNode(node);
        FFPAutomation.pushHistory();

        return node;
    };

    /**
     * Render a node to the canvas
     */
    FFPAutomation.renderNode = function(node) {
        var nodeType = FFPAutomation.nodeTypes[node.type];
        var $container = $('#ffp-workflow-nodes');

        var $node = $('<div class="ffp-node ffp-node-' + node.type + '" data-id="' + node.id + '">' +
            '<div class="ffp-node-header" style="background-color:' + nodeType.color + '">' +
            '<span class="ffp-node-icon dashicons dashicons-' + nodeType.icon + '"></span>' +
            '<span class="ffp-node-title">' + node.label + '</span>' +
            '</div>' +
            '<div class="ffp-node-body">' +
            FFPAutomation.renderNodeBody(node) +
            '</div>' +
            '<div class="ffp-node-ports">' +
            FFPAutomation.renderNodePorts(node, nodeType) +
            '</div>' +
            '</div>');

        $node.css({
            left: node.x + 'px',
            top: node.y + 'px'
        });

        // Make draggable
        FFPAutomation.makeNodeDraggable($node, node);

        // Selection
        $node.on('click', function(e) {
            e.stopPropagation();
            FFPAutomation.selectNode(node.id);
        });

        // Double click to edit
        $node.on('dblclick', function() {
            FFPAutomation.editNode(node.id);
        });

        $container.append($node);
    };

    /**
     * Render node body content
     */
    FFPAutomation.renderNodeBody = function(node) {
        var html = '';

        switch (node.type) {
            case 'condition':
                html = '<div class="ffp-node-summary">' +
                    (node.config.conditions && node.config.conditions.length ?
                        node.config.conditions.length + ' condition(s)' :
                        'No conditions') +
                    '</div>';
                break;
            case 'send_email':
                html = '<div class="ffp-node-summary">' +
                    (node.config.to || 'No recipient') +
                    '</div>';
                break;
            case 'delay':
                html = '<div class="ffp-node-summary">' +
                    (node.config.duration || 0) + ' ' + (node.config.unit || 'seconds') +
                    '</div>';
                break;
            case 'http_request':
                html = '<div class="ffp-node-summary">' +
                    (node.config.method || 'GET') + ' ' + (node.config.url || 'No URL') +
                    '</div>';
                break;
        }

        return html;
    };

    /**
     * Render node connection ports
     */
    FFPAutomation.renderNodePorts = function(node, nodeType) {
        var html = '';

        // Input ports
        if (nodeType.inputs > 0) {
            html += '<div class="ffp-port ffp-port-input" data-port="input"></div>';
        }

        // Output ports
        for (var i = 0; i < nodeType.outputs; i++) {
            var label = nodeType.outputs > 1 ? (i === 0 ? 'Yes' : 'No') : '';
            html += '<div class="ffp-port ffp-port-output" data-port="output-' + i + '" data-index="' + i + '">' +
                (label ? '<span class="ffp-port-label">' + label + '</span>' : '') +
                '</div>';
        }

        return html;
    };

    /**
     * Make node draggable
     */
    FFPAutomation.makeNodeDraggable = function($node, node) {
        var startX, startY, startNodeX, startNodeY;

        $node.on('mousedown', '.ffp-node-header', function(e) {
            if (e.button !== 0) return;
            e.preventDefault();
            e.stopPropagation();

            startX = e.clientX;
            startY = e.clientY;
            startNodeX = node.x;
            startNodeY = node.y;

            $node.addClass('ffp-node-dragging');
            FFPAutomation.state.isDragging = true;

            $(document).on('mousemove.nodedrag', function(e) {
                var dx = (e.clientX - startX) / FFPAutomation.state.zoom;
                var dy = (e.clientY - startY) / FFPAutomation.state.zoom;

                var newX = startNodeX + dx;
                var newY = startNodeY + dy;

                if (FFPAutomation.state.snapToGrid) {
                    newX = Math.round(newX / FFPAutomation.state.gridSize) * FFPAutomation.state.gridSize;
                    newY = Math.round(newY / FFPAutomation.state.gridSize) * FFPAutomation.state.gridSize;
                }

                node.x = Math.max(0, newX);
                node.y = Math.max(0, newY);

                $node.css({
                    left: node.x + 'px',
                    top: node.y + 'px'
                });

                FFPAutomation.updateConnections();
            });

            $(document).on('mouseup.nodedrag', function() {
                $(document).off('.nodedrag');
                $node.removeClass('ffp-node-dragging');
                FFPAutomation.state.isDragging = false;
                FFPAutomation.pushHistory();
            });
        });

        // Port connection handling
        $node.on('mousedown', '.ffp-port-output', function(e) {
            e.preventDefault();
            e.stopPropagation();
            FFPAutomation.startConnection(node.id, $(this).data('index'));
        });

        $node.on('mouseup', '.ffp-port-input', function(e) {
            e.stopPropagation();
            FFPAutomation.endConnection(node.id);
        });
    };

    /**
     * Start creating a connection
     */
    FFPAutomation.startConnection = function(fromNodeId, outputIndex) {
        FFPAutomation.state.pendingConnection = {
            from: fromNodeId,
            outputIndex: outputIndex
        };

        // Draw temporary line
        var $svg = $(FFPAutomation.svg);
        var tempLine = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        tempLine.setAttribute('class', 'ffp-connection-temp');
        tempLine.setAttribute('stroke', '#2271b1');
        tempLine.setAttribute('stroke-width', '2');
        tempLine.setAttribute('fill', 'none');
        tempLine.setAttribute('stroke-dasharray', '5,5');
        $svg.append(tempLine);

        $(document).on('mousemove.connection', function(e) {
            var rect = FFPAutomation.canvas.getBoundingClientRect();
            var endX = (e.clientX - rect.left - FFPAutomation.state.pan.x) / FFPAutomation.state.zoom;
            var endY = (e.clientY - rect.top - FFPAutomation.state.pan.y) / FFPAutomation.state.zoom;

            var fromNode = FFPAutomation.getNode(fromNodeId);
            var startX = fromNode.x + 120;
            var startY = fromNode.y + 40 + outputIndex * 30;

            var path = FFPAutomation.createConnectionPath(startX, startY, endX, endY);
            tempLine.setAttribute('d', path);
        });

        $(document).on('mouseup.connection', function() {
            $(document).off('.connection');
            $('.ffp-connection-temp').remove();
            FFPAutomation.state.pendingConnection = null;
        });
    };

    /**
     * End creating a connection
     */
    FFPAutomation.endConnection = function(toNodeId) {
        if (!FFPAutomation.state.pendingConnection) return;

        var fromNodeId = FFPAutomation.state.pendingConnection.from;
        var outputIndex = FFPAutomation.state.pendingConnection.outputIndex;

        // Don't connect to self
        if (fromNodeId === toNodeId) return;

        // Check if connection already exists
        var exists = FFPAutomation.state.connections.some(function(c) {
            return c.from === fromNodeId && c.to === toNodeId && c.outputIndex === outputIndex;
        });

        if (!exists) {
            FFPAutomation.addConnection(fromNodeId, toNodeId, outputIndex);
        }
    };

    /**
     * Cancel pending connection
     */
    FFPAutomation.cancelConnection = function() {
        $(document).off('.connection');
        $('.ffp-connection-temp').remove();
        FFPAutomation.state.pendingConnection = null;
    };

    /**
     * Add a connection between nodes
     */
    FFPAutomation.addConnection = function(fromId, toId, outputIndex) {
        outputIndex = outputIndex || 0;

        var connection = {
            id: 'conn-' + Date.now(),
            from: fromId,
            to: toId,
            outputIndex: outputIndex
        };

        FFPAutomation.state.connections.push(connection);
        FFPAutomation.renderConnection(connection);
        FFPAutomation.pushHistory();

        return connection;
    };

    /**
     * Render a connection line
     */
    FFPAutomation.renderConnection = function(connection) {
        var fromNode = FFPAutomation.getNode(connection.from);
        var toNode = FFPAutomation.getNode(connection.to);

        if (!fromNode || !toNode) return;

        var startX = fromNode.x + 120;
        var startY = fromNode.y + 40 + connection.outputIndex * 30;
        var endX = toNode.x;
        var endY = toNode.y + 20;

        var path = FFPAutomation.createConnectionPath(startX, startY, endX, endY);

        var $svg = $(FFPAutomation.svg);
        var line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        line.setAttribute('class', 'ffp-connection');
        line.setAttribute('data-id', connection.id);
        line.setAttribute('d', path);
        line.setAttribute('stroke', '#646970');
        line.setAttribute('stroke-width', '2');
        line.setAttribute('fill', 'none');
        line.setAttribute('marker-end', 'url(#ffp-arrow)');

        // Click to select/delete
        $(line).on('click', function(e) {
            e.stopPropagation();
            if (e.shiftKey) {
                FFPAutomation.deleteConnection(connection.id);
            }
        });

        $svg.append(line);
    };

    /**
     * Create bezier curve path for connection
     */
    FFPAutomation.createConnectionPath = function(x1, y1, x2, y2) {
        var dx = Math.abs(x2 - x1);
        var dy = Math.abs(y2 - y1);
        var curve = Math.min(dx, dy, 100);

        var cx1 = x1 + curve;
        var cy1 = y1;
        var cx2 = x2 - curve;
        var cy2 = y2;

        return 'M' + x1 + ',' + y1 + ' C' + cx1 + ',' + cy1 + ' ' + cx2 + ',' + cy2 + ' ' + x2 + ',' + y2;
    };

    /**
     * Update all connections
     */
    FFPAutomation.updateConnections = function() {
        FFPAutomation.state.connections.forEach(function(connection) {
            var $line = $('[data-id="' + connection.id + '"]');
            var fromNode = FFPAutomation.getNode(connection.from);
            var toNode = FFPAutomation.getNode(connection.to);

            if (!fromNode || !toNode) return;

            var startX = fromNode.x + 120;
            var startY = fromNode.y + 40 + connection.outputIndex * 30;
            var endX = toNode.x;
            var endY = toNode.y + 20;

            var path = FFPAutomation.createConnectionPath(startX, startY, endX, endY);
            $line.attr('d', path);
        });
    };

    /**
     * Get node by ID
     */
    FFPAutomation.getNode = function(id) {
        return FFPAutomation.state.nodes.find(function(n) { return n.id === id; });
    };

    /**
     * Select a node
     */
    FFPAutomation.selectNode = function(id) {
        FFPAutomation.deselectAll();
        var node = FFPAutomation.getNode(id);
        if (!node) return;

        FFPAutomation.state.selectedNode = node;
        $('[data-id="' + id + '"]').addClass('ffp-node-selected');
        FFPAutomation.showPropertyPanel(node);
    };

    /**
     * Deselect all nodes
     */
    FFPAutomation.deselectAll = function() {
        FFPAutomation.state.selectedNode = null;
        $('.ffp-node').removeClass('ffp-node-selected');
        FFPAutomation.hidePropertyPanel();
    };

    /**
     * Select all nodes
     */
    FFPAutomation.selectAll = function() {
        $('.ffp-node').addClass('ffp-node-selected');
    };

    /**
     * Delete selected nodes
     */
    FFPAutomation.deleteSelected = function() {
        if (FFPAutomation.state.selectedNode) {
            FFPAutomation.deleteNode(FFPAutomation.state.selectedNode.id);
        }
    };

    /**
     * Delete a node
     */
    FFPAutomation.deleteNode = function(id) {
        var node = FFPAutomation.getNode(id);
        if (!node || node.type === 'start') return; // Don't delete start node

        // Remove node from state
        FFPAutomation.state.nodes = FFPAutomation.state.nodes.filter(function(n) {
            return n.id !== id;
        });

        // Remove connections
        FFPAutomation.state.connections = FFPAutomation.state.connections.filter(function(c) {
            if (c.from === id || c.to === id) {
                $('[data-id="' + c.id + '"]').remove();
                return false;
            }
            return true;
        });

        // Remove DOM element
        $('[data-id="' + id + '"]').remove();

        if (FFPAutomation.state.selectedNode && FFPAutomation.state.selectedNode.id === id) {
            FFPAutomation.deselectAll();
        }

        FFPAutomation.pushHistory();
    };

    /**
     * Delete a connection
     */
    FFPAutomation.deleteConnection = function(id) {
        FFPAutomation.state.connections = FFPAutomation.state.connections.filter(function(c) {
            return c.id !== id;
        });
        $('[data-id="' + id + '"]').remove();
        FFPAutomation.pushHistory();
    };

    /**
     * Duplicate a node
     */
    FFPAutomation.duplicateNode = function(id) {
        var node = FFPAutomation.getNode(id);
        if (!node || node.type === 'start' || node.type === 'end') return;

        var newNode = FFPAutomation.addNode(node.type, node.x + 40, node.y + 40);
        if (newNode) {
            newNode.label = node.label;
            newNode.config = JSON.parse(JSON.stringify(node.config));
            FFPAutomation.refreshNode(newNode.id);
        }
    };

    /**
     * Duplicate selected node
     */
    FFPAutomation.duplicateSelected = function() {
        if (FFPAutomation.state.selectedNode) {
            FFPAutomation.duplicateNode(FFPAutomation.state.selectedNode.id);
        }
    };

    /**
     * Refresh node display
     */
    FFPAutomation.refreshNode = function(id) {
        var $node = $('[data-id="' + id + '"]');
        var node = FFPAutomation.getNode(id);
        if (!$node.length || !node) return;

        $node.find('.ffp-node-title').text(node.label);
        $node.find('.ffp-node-body').html(FFPAutomation.renderNodeBody(node));
    };

    /**
     * Show property panel for node
     */
    FFPAutomation.showPropertyPanel = function(node) {
        var nodeType = FFPAutomation.nodeTypes[node.type];
        var $panel = $('#ffp-property-panel');
        var $content = $panel.find('.ffp-property-content');

        $panel.find('.ffp-property-title').text(node.label);

        var html = '<div class="ffp-property-group">' +
            '<label>' + (ffpAutomation.strings.label || 'Label') + '</label>' +
            '<input type="text" name="label" value="' + node.label + '">' +
            '</div>';

        // Add type-specific fields
        html += FFPAutomation.renderPropertyFields(node);

        $content.html(html);
        $panel.addClass('active');
    };

    /**
     * Render property fields for node type
     */
    FFPAutomation.renderPropertyFields = function(node) {
        var html = '';

        switch (node.type) {
            case 'send_email':
                html += '<div class="ffp-property-group">' +
                    '<label>To</label>' +
                    '<input type="text" name="config.to" value="' + (node.config.to || '') + '" placeholder="{{email}}">' +
                    '</div>' +
                    '<div class="ffp-property-group">' +
                    '<label>Subject</label>' +
                    '<input type="text" name="config.subject" value="' + (node.config.subject || '') + '">' +
                    '</div>' +
                    '<div class="ffp-property-group">' +
                    '<label>Body</label>' +
                    '<textarea name="config.body" rows="5">' + (node.config.body || '') + '</textarea>' +
                    '</div>';
                break;

            case 'delay':
                html += '<div class="ffp-property-group">' +
                    '<label>Duration</label>' +
                    '<input type="number" name="config.duration" value="' + (node.config.duration || 60) + '">' +
                    '</div>' +
                    '<div class="ffp-property-group">' +
                    '<label>Unit</label>' +
                    '<select name="config.unit">' +
                    '<option value="seconds"' + (node.config.unit === 'seconds' ? ' selected' : '') + '>Seconds</option>' +
                    '<option value="minutes"' + (node.config.unit === 'minutes' ? ' selected' : '') + '>Minutes</option>' +
                    '<option value="hours"' + (node.config.unit === 'hours' ? ' selected' : '') + '>Hours</option>' +
                    '<option value="days"' + (node.config.unit === 'days' ? ' selected' : '') + '>Days</option>' +
                    '</select>' +
                    '</div>';
                break;

            case 'http_request':
                html += '<div class="ffp-property-group">' +
                    '<label>URL</label>' +
                    '<input type="text" name="config.url" value="' + (node.config.url || '') + '">' +
                    '</div>' +
                    '<div class="ffp-property-group">' +
                    '<label>Method</label>' +
                    '<select name="config.method">' +
                    '<option value="GET"' + (node.config.method === 'GET' ? ' selected' : '') + '>GET</option>' +
                    '<option value="POST"' + (node.config.method === 'POST' ? ' selected' : '') + '>POST</option>' +
                    '<option value="PUT"' + (node.config.method === 'PUT' ? ' selected' : '') + '>PUT</option>' +
                    '<option value="DELETE"' + (node.config.method === 'DELETE' ? ' selected' : '') + '>DELETE</option>' +
                    '</select>' +
                    '</div>' +
                    '<div class="ffp-property-group">' +
                    '<label>Body</label>' +
                    '<textarea name="config.body" rows="5">' + (node.config.body || '') + '</textarea>' +
                    '</div>';
                break;

            case 'condition':
                html += '<div class="ffp-property-group">' +
                    '<label>Expression</label>' +
                    '<input type="text" name="config.expression" value="' + (node.config.expression || '') + '" placeholder="{{field}} == value">' +
                    '</div>';
                break;
        }

        return html;
    };

    /**
     * Hide property panel
     */
    FFPAutomation.hidePropertyPanel = function() {
        $('#ffp-property-panel').removeClass('active');
    };

    /**
     * Update node configuration
     */
    FFPAutomation.updateNodeConfig = function(nodeId, field, value) {
        var node = FFPAutomation.getNode(nodeId);
        if (!node) return;

        if (field === 'label') {
            node.label = value;
        } else if (field.startsWith('config.')) {
            var configField = field.replace('config.', '');
            node.config[configField] = value;
        }

        FFPAutomation.refreshNode(nodeId);
        FFPAutomation.pushHistory();
    };

    /**
     * Auto layout nodes
     */
    FFPAutomation.autoLayout = function() {
        var startNode = FFPAutomation.state.nodes.find(function(n) { return n.type === 'start'; });
        if (!startNode) return;

        var visited = new Set();
        var levels = {};
        var nodesByLevel = {};

        function visit(nodeId, level) {
            if (visited.has(nodeId)) return;
            visited.add(nodeId);

            levels[nodeId] = Math.max(levels[nodeId] || 0, level);
            if (!nodesByLevel[level]) nodesByLevel[level] = [];
            if (!nodesByLevel[level].includes(nodeId)) {
                nodesByLevel[level].push(nodeId);
            }

            FFPAutomation.state.connections.filter(function(c) { return c.from === nodeId; }).forEach(function(c) {
                visit(c.to, level + 1);
            });
        }

        visit(startNode.id, 0);

        // Position nodes
        var xSpacing = 200;
        var ySpacing = 120;

        Object.keys(nodesByLevel).forEach(function(level) {
            var nodes = nodesByLevel[level];
            var totalHeight = (nodes.length - 1) * ySpacing;
            var startY = 100 + (level === '0' ? 0 : -totalHeight / 2 + (nodes.length > 1 ? 150 : 0));

            nodes.forEach(function(nodeId, index) {
                var node = FFPAutomation.getNode(nodeId);
                if (node) {
                    node.x = 100 + parseInt(level) * xSpacing;
                    node.y = startY + index * ySpacing;
                    $('[data-id="' + nodeId + '"]').css({
                        left: node.x + 'px',
                        top: node.y + 'px'
                    });
                }
            });
        });

        FFPAutomation.updateConnections();
        FFPAutomation.pushHistory();
    };

    /**
     * Save workflow
     */
    FFPAutomation.save = function() {
        var workflowData = {
            id: ffpAutomation.workflowId || 0,
            name: $('#ffp-workflow-name').val() || 'Untitled Workflow',
            description: $('#ffp-workflow-description').val() || '',
            status: $('#ffp-workflow-status').val() || 'draft',
            nodes: FFPAutomation.state.nodes,
            connections: FFPAutomation.state.connections
        };

        $.ajax({
            url: ffpAutomation.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ffp_save_workflow',
                nonce: ffpAutomation.nonce,
                workflow: JSON.stringify(workflowData)
            },
            success: function(response) {
                if (response.success) {
                    ffpAutomation.workflowId = response.data.id;
                    FFPAutomation.showNotice('success', ffpAutomation.strings.workflow_saved || 'Workflow saved!');
                } else {
                    FFPAutomation.showNotice('error', response.data.message || 'Error saving workflow');
                }
            },
            error: function() {
                FFPAutomation.showNotice('error', 'Network error');
            }
        });
    };

    /**
     * Load workflow
     */
    FFPAutomation.loadWorkflow = function(id) {
        $.ajax({
            url: ffpAutomation.restUrl + 'workflows/' + id,
            type: 'GET',
            headers: {
                'X-WP-Nonce': ffpAutomation.restNonce
            },
            success: function(response) {
                if (response.workflow) {
                    var workflow = response.workflow;
                    FFPAutomation.state.nodes = JSON.parse(workflow.nodes || '[]');
                    FFPAutomation.state.connections = JSON.parse(workflow.connections || '[]');

                    $('#ffp-workflow-name').val(workflow.name);
                    $('#ffp-workflow-description').val(workflow.description);
                    $('#ffp-workflow-status').val(workflow.status);

                    FFPAutomation.renderAll();
                }
            }
        });
    };

    /**
     * Render all nodes and connections
     */
    FFPAutomation.renderAll = function() {
        $('#ffp-workflow-nodes').empty();
        $(FFPAutomation.svg).find('.ffp-connection').remove();

        FFPAutomation.state.nodes.forEach(function(node) {
            FFPAutomation.renderNode(node);
        });

        FFPAutomation.state.connections.forEach(function(connection) {
            FFPAutomation.renderConnection(connection);
        });
    };

    /**
     * Test workflow
     */
    FFPAutomation.test = function() {
        if (!ffpAutomation.workflowId) {
            FFPAutomation.showNotice('warning', 'Save workflow before testing');
            return;
        }

        $.ajax({
            url: ffpAutomation.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ffp_test_workflow',
                nonce: ffpAutomation.nonce,
                workflow_id: ffpAutomation.workflowId,
                test_data: JSON.stringify({})
            },
            success: function(response) {
                if (response.success) {
                    FFPAutomation.showNotice('success', 'Test completed: ' + response.data.status);
                } else {
                    FFPAutomation.showNotice('error', response.data.message || 'Test failed');
                }
            }
        });
    };

    /**
     * History management
     */
    FFPAutomation.pushHistory = function() {
        var state = JSON.stringify({
            nodes: FFPAutomation.state.nodes,
            connections: FFPAutomation.state.connections
        });

        // Remove future states if we're not at the end
        FFPAutomation.state.history = FFPAutomation.state.history.slice(0, FFPAutomation.state.historyIndex + 1);

        FFPAutomation.state.history.push(state);
        FFPAutomation.state.historyIndex = FFPAutomation.state.history.length - 1;

        // Limit history size
        if (FFPAutomation.state.history.length > 50) {
            FFPAutomation.state.history.shift();
            FFPAutomation.state.historyIndex--;
        }

        FFPAutomation.updateUndoRedoButtons();
    };

    FFPAutomation.undo = function() {
        if (FFPAutomation.state.historyIndex > 0) {
            FFPAutomation.state.historyIndex--;
            FFPAutomation.restoreHistory();
        }
    };

    FFPAutomation.redo = function() {
        if (FFPAutomation.state.historyIndex < FFPAutomation.state.history.length - 1) {
            FFPAutomation.state.historyIndex++;
            FFPAutomation.restoreHistory();
        }
    };

    FFPAutomation.restoreHistory = function() {
        var state = JSON.parse(FFPAutomation.state.history[FFPAutomation.state.historyIndex]);
        FFPAutomation.state.nodes = state.nodes;
        FFPAutomation.state.connections = state.connections;
        FFPAutomation.renderAll();
        FFPAutomation.updateUndoRedoButtons();
    };

    FFPAutomation.updateUndoRedoButtons = function() {
        $('#ffp-undo').prop('disabled', FFPAutomation.state.historyIndex <= 0);
        $('#ffp-redo').prop('disabled', FFPAutomation.state.historyIndex >= FFPAutomation.state.history.length - 1);
    };

    /**
     * Show notification
     */
    FFPAutomation.showNotice = function(type, message) {
        var $notice = $('<div class="ffp-notice ffp-notice-' + type + '">' +
            '<span>' + message + '</span>' +
            '<button type="button" class="ffp-notice-dismiss">&times;</button>' +
            '</div>');

        $('#ffp-builder-notices').append($notice);

        $notice.find('.ffp-notice-dismiss').on('click', function() {
            $notice.fadeOut(function() { $(this).remove(); });
        });

        setTimeout(function() {
            $notice.fadeOut(function() { $(this).remove(); });
        }, 5000);
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        if ($('#ffp-workflow-canvas').length) {
            FFPAutomation.init();
        }
    });

})(jQuery);
