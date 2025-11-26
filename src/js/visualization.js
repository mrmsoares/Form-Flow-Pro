/**
 * FormFlow Pro - D3.js Visualization Library
 *
 * Interactive chart rendering system using D3.js v7
 * Supports: line, bar, pie, donut, area, scatter, heatmap, funnel, gauge, radial, treemap, sankey
 *
 * @package FormFlowPro
 * @since 2.3.0
 */

(function($, d3) {
    'use strict';

    // Namespace
    window.FFPVisualization = window.FFPVisualization || {};

    // Chart instances storage
    FFPVisualization.instances = {};
    FFPVisualization.charts = {};

    // Default configuration
    FFPVisualization.defaults = {
        margin: { top: 20, right: 30, bottom: 40, left: 50 },
        colors: ['#2271b1', '#72aee6', '#135e96', '#9ec2e6', '#c5d9ed', '#1d4e89', '#4a90d9', '#6ba3e0'],
        animation: {
            duration: 750,
            easing: d3.easeCubicOut
        },
        tooltip: {
            enabled: true,
            offset: { x: 10, y: -28 }
        },
        responsive: true
    };

    /**
     * Initialize visualization system
     */
    FFPVisualization.init = function() {
        // Process any queued charts
        if (window.FFPCharts) {
            Object.keys(window.FFPCharts).forEach(function(chartId) {
                FFPVisualization.render(chartId);
            });
        }

        // Setup resize handler for responsive charts
        FFPVisualization.setupResizeHandler();

        // Setup toolbar actions
        FFPVisualization.setupToolbarActions();
    };

    /**
     * Render a chart by ID
     */
    FFPVisualization.render = function(chartId) {
        var chartData = window.FFPCharts[chartId];
        if (!chartData || chartData.rendered) {
            return;
        }

        var config = chartData.config;
        var container = document.getElementById(chartData.container);

        if (!container) {
            console.warn('FFPVisualization: Container not found for chart', chartId);
            return;
        }

        // Clear container
        d3.select(container).selectAll('*').remove();

        // Get chart type and render
        var chartType = config.type || 'line';
        var renderMethod = 'render' + chartType.charAt(0).toUpperCase() + chartType.slice(1);

        if (typeof FFPVisualization[renderMethod] === 'function') {
            FFPVisualization.instances[chartId] = FFPVisualization[renderMethod](container, config);
            chartData.rendered = true;
        } else {
            console.warn('FFPVisualization: Unknown chart type', chartType);
        }
    };

    /**
     * Create tooltip
     */
    FFPVisualization.createTooltip = function() {
        var tooltip = d3.select('body').select('.ffp-tooltip');
        if (tooltip.empty()) {
            tooltip = d3.select('body')
                .append('div')
                .attr('class', 'ffp-tooltip')
                .style('opacity', 0)
                .style('position', 'absolute')
                .style('pointer-events', 'none');
        }
        return tooltip;
    };

    /**
     * Show tooltip
     */
    FFPVisualization.showTooltip = function(tooltip, html, event) {
        tooltip.transition()
            .duration(200)
            .style('opacity', 0.95);
        tooltip.html(html)
            .style('left', (event.pageX + FFPVisualization.defaults.tooltip.offset.x) + 'px')
            .style('top', (event.pageY + FFPVisualization.defaults.tooltip.offset.y) + 'px');
    };

    /**
     * Hide tooltip
     */
    FFPVisualization.hideTooltip = function(tooltip) {
        tooltip.transition()
            .duration(300)
            .style('opacity', 0);
    };

    /**
     * Get dimensions accounting for margins
     */
    FFPVisualization.getDimensions = function(container, config) {
        var margin = config.options.margin || FFPVisualization.defaults.margin;
        var width = container.clientWidth - margin.left - margin.right;
        var height = (config.dimensions.height || 400) - margin.top - margin.bottom;
        return { width: width, height: height, margin: margin };
    };

    /**
     * Format number with locale
     */
    FFPVisualization.formatNumber = function(value, format) {
        if (format === 'currency') {
            return '$' + d3.format(',.2f')(value);
        } else if (format === 'percent') {
            return d3.format('.1f')(value) + '%';
        } else if (format === 'compact') {
            return d3.format('.2s')(value);
        }
        return d3.format(',')(value);
    };

    // ==================== Line Chart ====================

    FFPVisualization.renderLine = function(container, config) {
        var dim = FFPVisualization.getDimensions(container, config);
        var data = config.data || [];
        var colors = config.colors || FFPVisualization.defaults.colors;
        var options = config.options || {};

        // Parse dates if needed
        var parseDate = d3.timeParse('%Y-%m-%d');
        data.forEach(function(d) {
            if (typeof d.date === 'string') {
                d.parsedDate = parseDate(d.date) || new Date(d.date);
            } else {
                d.parsedDate = d.date;
            }
        });

        // Create SVG
        var svg = d3.select(container)
            .append('svg')
            .attr('width', dim.width + dim.margin.left + dim.margin.right)
            .attr('height', dim.height + dim.margin.top + dim.margin.bottom)
            .append('g')
            .attr('transform', 'translate(' + dim.margin.left + ',' + dim.margin.top + ')');

        // Scales
        var x = d3.scaleTime()
            .domain(d3.extent(data, function(d) { return d.parsedDate; }))
            .range([0, dim.width]);

        var y = d3.scaleLinear()
            .domain([0, d3.max(data, function(d) { return d.value; }) * 1.1])
            .range([dim.height, 0]);

        // Grid lines
        svg.append('g')
            .attr('class', 'ffp-grid ffp-grid-y')
            .call(d3.axisLeft(y)
                .tickSize(-dim.width)
                .tickFormat('')
            );

        // Line generator
        var line = d3.line()
            .x(function(d) { return x(d.parsedDate); })
            .y(function(d) { return y(d.value); })
            .curve(d3.curveMonotoneX);

        // Area fill (optional)
        if (options.areaOpacity > 0) {
            var area = d3.area()
                .x(function(d) { return x(d.parsedDate); })
                .y0(dim.height)
                .y1(function(d) { return y(d.value); })
                .curve(d3.curveMonotoneX);

            svg.append('path')
                .datum(data)
                .attr('class', 'ffp-area')
                .attr('fill', colors[0])
                .attr('fill-opacity', options.areaOpacity || 0.2)
                .attr('d', area);
        }

        // Draw line with animation
        var path = svg.append('path')
            .datum(data)
            .attr('class', 'ffp-line')
            .attr('fill', 'none')
            .attr('stroke', colors[0])
            .attr('stroke-width', options.lineWidth || 2)
            .attr('d', line);

        // Animate line drawing
        var totalLength = path.node().getTotalLength();
        path.attr('stroke-dasharray', totalLength + ' ' + totalLength)
            .attr('stroke-dashoffset', totalLength)
            .transition()
            .duration(FFPVisualization.defaults.animation.duration)
            .ease(FFPVisualization.defaults.animation.easing)
            .attr('stroke-dashoffset', 0);

        // Points
        if (options.showPoints !== false) {
            var tooltip = FFPVisualization.createTooltip();

            svg.selectAll('.ffp-point')
                .data(data)
                .enter()
                .append('circle')
                .attr('class', 'ffp-point')
                .attr('cx', function(d) { return x(d.parsedDate); })
                .attr('cy', function(d) { return y(d.value); })
                .attr('r', 0)
                .attr('fill', colors[0])
                .attr('stroke', '#fff')
                .attr('stroke-width', 2)
                .on('mouseover', function(event, d) {
                    d3.select(this).transition().duration(200).attr('r', (options.pointRadius || 4) + 2);
                    FFPVisualization.showTooltip(tooltip,
                        '<strong>' + d3.timeFormat('%b %d, %Y')(d.parsedDate) + '</strong><br/>' +
                        FFPVisualization.formatNumber(d.value),
                        event
                    );
                })
                .on('mouseout', function() {
                    d3.select(this).transition().duration(200).attr('r', options.pointRadius || 4);
                    FFPVisualization.hideTooltip(tooltip);
                })
                .transition()
                .delay(function(d, i) { return i * 50; })
                .duration(300)
                .attr('r', options.pointRadius || 4);
        }

        // X Axis
        svg.append('g')
            .attr('class', 'ffp-axis ffp-axis-x')
            .attr('transform', 'translate(0,' + dim.height + ')')
            .call(d3.axisBottom(x).ticks(6).tickFormat(d3.timeFormat('%b %d')));

        // Y Axis
        svg.append('g')
            .attr('class', 'ffp-axis ffp-axis-y')
            .call(d3.axisLeft(y).ticks(5));

        return svg;
    };

    // ==================== Bar Chart ====================

    FFPVisualization.renderBar = function(container, config) {
        var dim = FFPVisualization.getDimensions(container, config);
        var data = config.data || [];
        var colors = config.colors || FFPVisualization.defaults.colors;
        var options = config.options || {};
        var horizontal = options.orientation === 'horizontal';

        var svg = d3.select(container)
            .append('svg')
            .attr('width', dim.width + dim.margin.left + dim.margin.right)
            .attr('height', dim.height + dim.margin.top + dim.margin.bottom)
            .append('g')
            .attr('transform', 'translate(' + dim.margin.left + ',' + dim.margin.top + ')');

        var x, y;

        if (horizontal) {
            x = d3.scaleLinear()
                .domain([0, d3.max(data, function(d) { return d.value; }) * 1.1])
                .range([0, dim.width]);

            y = d3.scaleBand()
                .domain(data.map(function(d) { return d.label; }))
                .range([0, dim.height])
                .padding(options.barPadding || 0.2);
        } else {
            x = d3.scaleBand()
                .domain(data.map(function(d) { return d.label; }))
                .range([0, dim.width])
                .padding(options.barPadding || 0.2);

            y = d3.scaleLinear()
                .domain([0, d3.max(data, function(d) { return d.value; }) * 1.1])
                .range([dim.height, 0]);
        }

        // Grid
        svg.append('g')
            .attr('class', 'ffp-grid')
            .call(horizontal ?
                d3.axisBottom(x).tickSize(dim.height).tickFormat('') :
                d3.axisLeft(y).tickSize(-dim.width).tickFormat('')
            );

        // Tooltip
        var tooltip = FFPVisualization.createTooltip();

        // Bars
        var bars = svg.selectAll('.ffp-bar')
            .data(data)
            .enter()
            .append('rect')
            .attr('class', 'ffp-bar')
            .attr('fill', function(d, i) { return colors[i % colors.length]; })
            .attr('rx', options.borderRadius || 4)
            .attr('ry', options.borderRadius || 4);

        if (horizontal) {
            bars.attr('x', 0)
                .attr('y', function(d) { return y(d.label); })
                .attr('height', y.bandwidth())
                .attr('width', 0)
                .transition()
                .duration(FFPVisualization.defaults.animation.duration)
                .delay(function(d, i) { return i * 50; })
                .attr('width', function(d) { return x(d.value); });
        } else {
            bars.attr('x', function(d) { return x(d.label); })
                .attr('y', dim.height)
                .attr('width', x.bandwidth())
                .attr('height', 0)
                .transition()
                .duration(FFPVisualization.defaults.animation.duration)
                .delay(function(d, i) { return i * 50; })
                .attr('y', function(d) { return y(d.value); })
                .attr('height', function(d) { return dim.height - y(d.value); });
        }

        // Tooltip events
        bars.on('mouseover', function(event, d) {
                d3.select(this).attr('opacity', 0.8);
                FFPVisualization.showTooltip(tooltip,
                    '<strong>' + d.label + '</strong><br/>' +
                    FFPVisualization.formatNumber(d.value),
                    event
                );
            })
            .on('mouseout', function() {
                d3.select(this).attr('opacity', 1);
                FFPVisualization.hideTooltip(tooltip);
            });

        // Value labels
        if (options.showValues) {
            svg.selectAll('.ffp-bar-value')
                .data(data)
                .enter()
                .append('text')
                .attr('class', 'ffp-bar-value')
                .attr('text-anchor', horizontal ? 'start' : 'middle')
                .attr('x', function(d) {
                    return horizontal ? x(d.value) + 5 : x(d.label) + x.bandwidth() / 2;
                })
                .attr('y', function(d) {
                    return horizontal ? y(d.label) + y.bandwidth() / 2 : y(d.value) - 5;
                })
                .attr('dy', horizontal ? '.35em' : 0)
                .attr('fill', '#333')
                .attr('font-size', '12px')
                .text(function(d) { return FFPVisualization.formatNumber(d.value); });
        }

        // Axes
        svg.append('g')
            .attr('class', 'ffp-axis ffp-axis-x')
            .attr('transform', 'translate(0,' + dim.height + ')')
            .call(d3.axisBottom(horizontal ? x : x))
            .selectAll('text')
            .attr('transform', horizontal ? '' : 'rotate(-45)')
            .style('text-anchor', horizontal ? 'middle' : 'end');

        svg.append('g')
            .attr('class', 'ffp-axis ffp-axis-y')
            .call(d3.axisLeft(horizontal ? y : y));

        return svg;
    };

    // ==================== Pie/Donut Chart ====================

    FFPVisualization.renderPie = function(container, config) {
        return FFPVisualization.renderPieDonut(container, config, false);
    };

    FFPVisualization.renderDonut = function(container, config) {
        return FFPVisualization.renderPieDonut(container, config, true);
    };

    FFPVisualization.renderPieDonut = function(container, config, isDonut) {
        var width = container.clientWidth;
        var height = config.dimensions.height || 400;
        var radius = Math.min(width, height) / 2 - 40;
        var data = config.data || [];
        var colors = config.colors || FFPVisualization.defaults.colors;
        var options = config.options || {};

        var innerRadius = isDonut ? (options.innerRadius || radius * 0.5) : 0;
        var outerRadius = options.outerRadius || radius;

        var svg = d3.select(container)
            .append('svg')
            .attr('width', width)
            .attr('height', height)
            .append('g')
            .attr('transform', 'translate(' + width / 2 + ',' + height / 2 + ')');

        // Pie layout
        var pie = d3.pie()
            .value(function(d) { return d.value; })
            .sort(null)
            .padAngle(options.padAngle || 0.02);

        // Arc generators
        var arc = d3.arc()
            .innerRadius(innerRadius)
            .outerRadius(outerRadius)
            .cornerRadius(options.cornerRadius || 4);

        var arcHover = d3.arc()
            .innerRadius(innerRadius)
            .outerRadius(outerRadius + 10)
            .cornerRadius(options.cornerRadius || 4);

        var arcLabel = d3.arc()
            .innerRadius(outerRadius * 0.7)
            .outerRadius(outerRadius * 0.7);

        // Tooltip
        var tooltip = FFPVisualization.createTooltip();
        var total = d3.sum(data, function(d) { return d.value; });

        // Draw slices
        var slices = svg.selectAll('.ffp-slice')
            .data(pie(data))
            .enter()
            .append('g')
            .attr('class', 'ffp-slice');

        slices.append('path')
            .attr('d', arc)
            .attr('fill', function(d, i) { return colors[i % colors.length]; })
            .attr('stroke', '#fff')
            .attr('stroke-width', 2)
            .style('opacity', 0)
            .on('mouseover', function(event, d) {
                d3.select(this).transition().duration(200).attr('d', arcHover);
                var percent = ((d.data.value / total) * 100).toFixed(1);
                FFPVisualization.showTooltip(tooltip,
                    '<strong>' + d.data.label + '</strong><br/>' +
                    FFPVisualization.formatNumber(d.data.value) + ' (' + percent + '%)',
                    event
                );
            })
            .on('mouseout', function() {
                d3.select(this).transition().duration(200).attr('d', arc);
                FFPVisualization.hideTooltip(tooltip);
            })
            .transition()
            .duration(FFPVisualization.defaults.animation.duration)
            .attrTween('d', function(d) {
                var interpolate = d3.interpolate({ startAngle: 0, endAngle: 0 }, d);
                return function(t) { return arc(interpolate(t)); };
            })
            .style('opacity', 1);

        // Labels
        if (options.showLabels !== false) {
            slices.append('text')
                .attr('transform', function(d) { return 'translate(' + arcLabel.centroid(d) + ')'; })
                .attr('text-anchor', 'middle')
                .attr('font-size', '12px')
                .attr('fill', '#fff')
                .attr('font-weight', '600')
                .style('opacity', 0)
                .text(function(d) {
                    var percent = ((d.data.value / total) * 100).toFixed(0);
                    return percent > 5 ? percent + '%' : '';
                })
                .transition()
                .delay(FFPVisualization.defaults.animation.duration)
                .duration(300)
                .style('opacity', 1);
        }

        // Center label for donut
        if (isDonut && options.centerLabel) {
            svg.append('text')
                .attr('class', 'ffp-donut-center')
                .attr('text-anchor', 'middle')
                .attr('dy', '0.35em')
                .attr('font-size', '24px')
                .attr('font-weight', 'bold')
                .attr('fill', '#1d2327')
                .text(options.centerLabel);
        }

        // Legend
        FFPVisualization.renderLegend(container, data, colors, config);

        return svg;
    };

    // ==================== Area Chart ====================

    FFPVisualization.renderArea = function(container, config) {
        config.options = config.options || {};
        config.options.areaOpacity = config.options.areaOpacity || 0.6;
        return FFPVisualization.renderLine(container, config);
    };

    // ==================== Heatmap ====================

    FFPVisualization.renderHeatmap = function(container, config) {
        var dim = FFPVisualization.getDimensions(container, config);
        dim.margin.left = 100;
        dim.margin.bottom = 60;
        dim.width = container.clientWidth - dim.margin.left - dim.margin.right;

        var data = config.data || [];
        var options = config.options || {};

        var svg = d3.select(container)
            .append('svg')
            .attr('width', dim.width + dim.margin.left + dim.margin.right)
            .attr('height', dim.height + dim.margin.top + dim.margin.bottom)
            .append('g')
            .attr('transform', 'translate(' + dim.margin.left + ',' + dim.margin.top + ')');

        // Get unique x and y values
        var xLabels = [...new Set(data.map(function(d) { return d.x; }))];
        var yLabels = [...new Set(data.map(function(d) { return d.y; }))];

        // Scales
        var x = d3.scaleBand()
            .domain(xLabels)
            .range([0, dim.width])
            .padding(0.05);

        var y = d3.scaleBand()
            .domain(yLabels)
            .range([0, dim.height])
            .padding(0.05);

        var colorScale = options.colorScale || ['#f7fbff', '#2271b1'];
        var color = d3.scaleSequential()
            .domain([0, d3.max(data, function(d) { return d.value; })])
            .interpolator(d3.interpolateRgbBasis(colorScale));

        // Tooltip
        var tooltip = FFPVisualization.createTooltip();

        // Cells
        svg.selectAll('.ffp-cell')
            .data(data)
            .enter()
            .append('rect')
            .attr('class', 'ffp-cell')
            .attr('x', function(d) { return x(d.x); })
            .attr('y', function(d) { return y(d.y); })
            .attr('width', x.bandwidth())
            .attr('height', y.bandwidth())
            .attr('rx', options.cellRadius || 2)
            .attr('fill', function(d) { return color(d.value); })
            .style('opacity', 0)
            .on('mouseover', function(event, d) {
                d3.select(this).attr('stroke', '#333').attr('stroke-width', 2);
                FFPVisualization.showTooltip(tooltip,
                    '<strong>' + d.x + ' / ' + d.y + '</strong><br/>' +
                    FFPVisualization.formatNumber(d.value),
                    event
                );
            })
            .on('mouseout', function() {
                d3.select(this).attr('stroke', 'none');
                FFPVisualization.hideTooltip(tooltip);
            })
            .transition()
            .duration(FFPVisualization.defaults.animation.duration)
            .delay(function(d, i) { return i * 10; })
            .style('opacity', 1);

        // Value labels
        if (options.showValues !== false) {
            var maxValue = d3.max(data, function(d) { return d.value; });
            svg.selectAll('.ffp-cell-value')
                .data(data)
                .enter()
                .append('text')
                .attr('class', 'ffp-cell-value')
                .attr('x', function(d) { return x(d.x) + x.bandwidth() / 2; })
                .attr('y', function(d) { return y(d.y) + y.bandwidth() / 2; })
                .attr('text-anchor', 'middle')
                .attr('dominant-baseline', 'middle')
                .attr('font-size', '11px')
                .attr('fill', function(d) { return d.value > maxValue / 2 ? '#fff' : '#333'; })
                .text(function(d) { return d.value; });
        }

        // Axes
        svg.append('g')
            .attr('class', 'ffp-axis ffp-axis-x')
            .attr('transform', 'translate(0,' + dim.height + ')')
            .call(d3.axisBottom(x))
            .selectAll('text')
            .attr('transform', 'rotate(-45)')
            .style('text-anchor', 'end');

        svg.append('g')
            .attr('class', 'ffp-axis ffp-axis-y')
            .call(d3.axisLeft(y));

        return svg;
    };

    // ==================== Funnel Chart ====================

    FFPVisualization.renderFunnel = function(container, config) {
        var dim = FFPVisualization.getDimensions(container, config);
        dim.margin.left = 120;
        dim.margin.right = 120;
        dim.width = container.clientWidth - dim.margin.left - dim.margin.right;

        var data = config.data || [];
        var colors = config.colors || FFPVisualization.defaults.colors;
        var options = config.options || {};

        var svg = d3.select(container)
            .append('svg')
            .attr('width', dim.width + dim.margin.left + dim.margin.right)
            .attr('height', dim.height + dim.margin.top + dim.margin.bottom)
            .append('g')
            .attr('transform', 'translate(' + dim.margin.left + ',' + dim.margin.top + ')');

        var maxValue = data[0] ? data[0].value : 0;
        var stepHeight = dim.height / data.length;

        // Tooltip
        var tooltip = FFPVisualization.createTooltip();

        data.forEach(function(d, i) {
            var topWidth = (d.value / maxValue) * dim.width;
            var nextValue = data[i + 1] ? data[i + 1].value : d.value * 0.7;
            var bottomWidth = (nextValue / maxValue) * dim.width;

            var topLeft = (dim.width - topWidth) / 2;
            var topRight = topLeft + topWidth;
            var bottomLeft = (dim.width - bottomWidth) / 2;
            var bottomRight = bottomLeft + bottomWidth;

            var y1 = i * stepHeight;
            var y2 = (i + 1) * stepHeight;

            // Trapezoid path
            var pathData = 'M' + topLeft + ',' + y1 +
                ' L' + topRight + ',' + y1 +
                ' L' + bottomRight + ',' + y2 +
                ' L' + bottomLeft + ',' + y2 + ' Z';

            svg.append('path')
                .attr('class', 'ffp-funnel-segment')
                .attr('d', pathData)
                .attr('fill', colors[i % colors.length])
                .attr('stroke', '#fff')
                .attr('stroke-width', 2)
                .style('opacity', 0)
                .on('mouseover', function(event) {
                    d3.select(this).attr('opacity', 0.8);
                    var percent = ((d.value / maxValue) * 100).toFixed(1);
                    FFPVisualization.showTooltip(tooltip,
                        '<strong>' + d.label + '</strong><br/>' +
                        FFPVisualization.formatNumber(d.value) + ' (' + percent + '%)',
                        event
                    );
                })
                .on('mouseout', function() {
                    d3.select(this).attr('opacity', 1);
                    FFPVisualization.hideTooltip(tooltip);
                })
                .transition()
                .delay(i * 150)
                .duration(500)
                .style('opacity', 1);

            // Left label
            svg.append('text')
                .attr('class', 'ffp-funnel-label')
                .attr('x', -10)
                .attr('y', y1 + stepHeight / 2)
                .attr('text-anchor', 'end')
                .attr('dominant-baseline', 'middle')
                .attr('font-size', '13px')
                .attr('font-weight', '600')
                .attr('fill', '#1d2327')
                .text(d.label);

            // Right value
            var percent = ((d.value / maxValue) * 100).toFixed(0);
            svg.append('text')
                .attr('class', 'ffp-funnel-value')
                .attr('x', dim.width + 10)
                .attr('y', y1 + stepHeight / 2)
                .attr('text-anchor', 'start')
                .attr('dominant-baseline', 'middle')
                .attr('font-size', '12px')
                .attr('fill', '#646970')
                .text(FFPVisualization.formatNumber(d.value) + ' (' + percent + '%)');
        });

        return svg;
    };

    // ==================== Gauge Chart ====================

    FFPVisualization.renderGauge = function(container, config) {
        var width = container.clientWidth;
        var height = config.dimensions.height || 300;
        var radius = Math.min(width, height * 2) / 2 - 20;
        var data = config.data || [];
        var options = config.options || {};

        var value = data[0] ? data[0].value : 0;
        var min = options.min || 0;
        var max = options.max || 100;
        var startAngle = (options.startAngle || -120) * (Math.PI / 180);
        var endAngle = (options.endAngle || 120) * (Math.PI / 180);

        var svg = d3.select(container)
            .append('svg')
            .attr('width', width)
            .attr('height', height)
            .append('g')
            .attr('transform', 'translate(' + width / 2 + ',' + (height - 30) + ')');

        // Background arc
        var bgArc = d3.arc()
            .innerRadius(radius - 30)
            .outerRadius(radius)
            .startAngle(startAngle)
            .endAngle(endAngle)
            .cornerRadius(5);

        svg.append('path')
            .attr('class', 'ffp-gauge-bg')
            .attr('d', bgArc)
            .attr('fill', '#e9ecef');

        // Value arc
        var valueScale = d3.scaleLinear()
            .domain([min, max])
            .range([startAngle, endAngle]);

        var valueArc = d3.arc()
            .innerRadius(radius - 30)
            .outerRadius(radius)
            .startAngle(startAngle)
            .cornerRadius(5);

        // Determine color based on thresholds
        var thresholds = options.thresholds || [
            { value: 30, color: '#dc3545' },
            { value: 70, color: '#ffc107' },
            { value: 100, color: '#28a745' }
        ];

        var gaugeColor = thresholds[thresholds.length - 1].color;
        for (var i = 0; i < thresholds.length; i++) {
            if (value <= thresholds[i].value) {
                gaugeColor = thresholds[i].color;
                break;
            }
        }

        svg.append('path')
            .attr('class', 'ffp-gauge-value')
            .attr('fill', gaugeColor)
            .transition()
            .duration(FFPVisualization.defaults.animation.duration)
            .attrTween('d', function() {
                var interpolate = d3.interpolate(startAngle, valueScale(value));
                return function(t) {
                    valueArc.endAngle(interpolate(t));
                    return valueArc();
                };
            });

        // Center text
        svg.append('text')
            .attr('class', 'ffp-gauge-text')
            .attr('text-anchor', 'middle')
            .attr('dy', '-0.5em')
            .attr('font-size', '36px')
            .attr('font-weight', 'bold')
            .attr('fill', gaugeColor)
            .text(Math.round(value));

        if (data[0] && data[0].label) {
            svg.append('text')
                .attr('class', 'ffp-gauge-label')
                .attr('text-anchor', 'middle')
                .attr('dy', '1.5em')
                .attr('font-size', '14px')
                .attr('fill', '#646970')
                .text(data[0].label);
        }

        // Min/Max labels
        svg.append('text')
            .attr('x', -radius + 10)
            .attr('y', 20)
            .attr('font-size', '12px')
            .attr('fill', '#646970')
            .text(min);

        svg.append('text')
            .attr('x', radius - 10)
            .attr('y', 20)
            .attr('text-anchor', 'end')
            .attr('font-size', '12px')
            .attr('fill', '#646970')
            .text(max);

        return svg;
    };

    // ==================== Scatter Plot ====================

    FFPVisualization.renderScatter = function(container, config) {
        var dim = FFPVisualization.getDimensions(container, config);
        var data = config.data || [];
        var colors = config.colors || FFPVisualization.defaults.colors;
        var options = config.options || {};

        var svg = d3.select(container)
            .append('svg')
            .attr('width', dim.width + dim.margin.left + dim.margin.right)
            .attr('height', dim.height + dim.margin.top + dim.margin.bottom)
            .append('g')
            .attr('transform', 'translate(' + dim.margin.left + ',' + dim.margin.top + ')');

        // Scales
        var x = d3.scaleLinear()
            .domain([0, d3.max(data, function(d) { return d.x; }) * 1.1])
            .range([0, dim.width]);

        var y = d3.scaleLinear()
            .domain([0, d3.max(data, function(d) { return d.y; }) * 1.1])
            .range([dim.height, 0]);

        // Grid
        svg.append('g')
            .attr('class', 'ffp-grid')
            .call(d3.axisLeft(y).tickSize(-dim.width).tickFormat(''));

        // Tooltip
        var tooltip = FFPVisualization.createTooltip();

        // Points
        svg.selectAll('.ffp-scatter-point')
            .data(data)
            .enter()
            .append('circle')
            .attr('class', 'ffp-scatter-point')
            .attr('cx', function(d) { return x(d.x); })
            .attr('cy', function(d) { return y(d.y); })
            .attr('r', 0)
            .attr('fill', function(d, i) { return d.color || colors[i % colors.length]; })
            .attr('opacity', options.opacity || 0.7)
            .on('mouseover', function(event, d) {
                d3.select(this).transition().duration(200).attr('r', (options.pointRadius || 6) + 3);
                FFPVisualization.showTooltip(tooltip,
                    '<strong>' + (d.label || '') + '</strong><br/>' +
                    'X: ' + d.x + '<br/>Y: ' + d.y,
                    event
                );
            })
            .on('mouseout', function() {
                d3.select(this).transition().duration(200).attr('r', options.pointRadius || 6);
                FFPVisualization.hideTooltip(tooltip);
            })
            .transition()
            .duration(FFPVisualization.defaults.animation.duration)
            .delay(function(d, i) { return i * 20; })
            .attr('r', options.pointRadius || 6);

        // Axes
        svg.append('g')
            .attr('class', 'ffp-axis ffp-axis-x')
            .attr('transform', 'translate(0,' + dim.height + ')')
            .call(d3.axisBottom(x));

        svg.append('g')
            .attr('class', 'ffp-axis ffp-axis-y')
            .call(d3.axisLeft(y));

        return svg;
    };

    // ==================== Radial Bar Chart ====================

    FFPVisualization.renderRadial_bar = function(container, config) {
        var width = container.clientWidth;
        var height = config.dimensions.height || 400;
        var data = config.data || [];
        var colors = config.colors || FFPVisualization.defaults.colors;
        var options = config.options || {};

        var innerRadius = options.innerRadius || 40;
        var barWidth = options.barWidth || 20;

        var svg = d3.select(container)
            .append('svg')
            .attr('width', width)
            .attr('height', height)
            .append('g')
            .attr('transform', 'translate(' + width / 2 + ',' + height / 2 + ')');

        var maxValue = d3.max(data, function(d) { return d.value; });
        var angleScale = d3.scaleLinear()
            .domain([0, maxValue])
            .range([0, 2 * Math.PI]);

        // Tooltip
        var tooltip = FFPVisualization.createTooltip();

        data.forEach(function(d, i) {
            var outerRadius = innerRadius + (i + 1) * (barWidth + 5);

            // Track
            if (options.showTrack !== false) {
                svg.append('path')
                    .attr('class', 'ffp-radial-track')
                    .attr('d', d3.arc()
                        .innerRadius(outerRadius - barWidth)
                        .outerRadius(outerRadius)
                        .startAngle(0)
                        .endAngle(2 * Math.PI)
                        .cornerRadius(options.cornerRadius || 10)
                    )
                    .attr('fill', options.trackColor || '#e9ecef');
            }

            // Value arc
            var arc = d3.arc()
                .innerRadius(outerRadius - barWidth)
                .outerRadius(outerRadius)
                .startAngle(0)
                .cornerRadius(options.cornerRadius || 10);

            svg.append('path')
                .attr('class', 'ffp-radial-bar')
                .attr('fill', colors[i % colors.length])
                .on('mouseover', function(event) {
                    var percent = ((d.value / maxValue) * 100).toFixed(1);
                    FFPVisualization.showTooltip(tooltip,
                        '<strong>' + d.label + '</strong><br/>' +
                        FFPVisualization.formatNumber(d.value) + ' (' + percent + '%)',
                        event
                    );
                })
                .on('mouseout', function() {
                    FFPVisualization.hideTooltip(tooltip);
                })
                .transition()
                .duration(FFPVisualization.defaults.animation.duration)
                .delay(i * 100)
                .attrTween('d', function() {
                    var interpolate = d3.interpolate(0, angleScale(d.value));
                    return function(t) {
                        arc.endAngle(interpolate(t));
                        return arc();
                    };
                });

            // Label
            svg.append('text')
                .attr('class', 'ffp-radial-label')
                .attr('x', -width / 2 + 10)
                .attr('y', -outerRadius + barWidth / 2 + 5)
                .attr('font-size', '12px')
                .attr('fill', '#646970')
                .text(d.label);
        });

        return svg;
    };

    // ==================== Legend ====================

    FFPVisualization.renderLegend = function(container, data, colors, config) {
        if (config.legend && config.legend.show === false) {
            return;
        }

        var legendContainer = d3.select(container).select('.ffp-chart-legend');
        if (legendContainer.empty()) {
            legendContainer = d3.select(container.parentNode).select('.ffp-chart-legend');
        }

        if (legendContainer.empty()) {
            return;
        }

        legendContainer.selectAll('.ffp-legend-item')
            .data(data)
            .enter()
            .append('div')
            .attr('class', 'ffp-legend-item')
            .html(function(d, i) {
                return '<span class="ffp-legend-color" style="background:' + colors[i % colors.length] + '"></span>' +
                    '<span class="ffp-legend-label">' + d.label + '</span>';
            });
    };

    // ==================== Resize Handler ====================

    FFPVisualization.setupResizeHandler = function() {
        var resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                Object.keys(window.FFPCharts || {}).forEach(function(chartId) {
                    var chartData = window.FFPCharts[chartId];
                    if (chartData && chartData.config.responsive) {
                        chartData.rendered = false;
                        FFPVisualization.render(chartId);
                    }
                });
            }, 250);
        });
    };

    // ==================== Toolbar Actions ====================

    FFPVisualization.setupToolbarActions = function() {
        $(document).on('click', '.ffp-chart-action', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var action = $btn.data('action');
            var $wrapper = $btn.closest('.ffp-chart-wrapper');
            var chartId = $wrapper.data('chart-id');

            switch (action) {
                case 'zoom-in':
                    FFPVisualization.zoom(chartId, 1.2);
                    break;
                case 'zoom-out':
                    FFPVisualization.zoom(chartId, 0.8);
                    break;
                case 'reset':
                    FFPVisualization.reset(chartId);
                    break;
                case 'download':
                    FFPVisualization.download(chartId);
                    break;
                case 'fullscreen':
                    FFPVisualization.fullscreen($wrapper);
                    break;
            }
        });
    };

    FFPVisualization.zoom = function(chartId, factor) {
        var svg = FFPVisualization.instances[chartId];
        if (!svg) return;

        var currentTransform = svg.attr('transform') || '';
        var match = currentTransform.match(/scale\(([^)]+)\)/);
        var currentScale = match ? parseFloat(match[1]) : 1;
        var newScale = Math.max(0.5, Math.min(3, currentScale * factor));

        svg.transition().duration(300).attr('transform', currentTransform.replace(/scale\([^)]+\)/, '') + ' scale(' + newScale + ')');
    };

    FFPVisualization.reset = function(chartId) {
        window.FFPCharts[chartId].rendered = false;
        FFPVisualization.render(chartId);
    };

    FFPVisualization.download = function(chartId) {
        var container = document.getElementById('ffp-chart-' + chartId);
        if (!container) return;

        var svg = container.querySelector('svg');
        if (!svg) return;

        // Clone SVG
        var clone = svg.cloneNode(true);
        clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');

        // Serialize
        var svgData = new XMLSerializer().serializeToString(clone);
        var blob = new Blob([svgData], { type: 'image/svg+xml' });
        var url = URL.createObjectURL(blob);

        // Download
        var a = document.createElement('a');
        a.href = url;
        a.download = 'chart-' + chartId + '.svg';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    FFPVisualization.fullscreen = function($wrapper) {
        var elem = $wrapper[0];
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) {
            elem.msRequestFullscreen();
        }
    };

    // ==================== Data Loading ====================

    FFPVisualization.loadData = function(chartId, params) {
        params = params || {};

        return $.ajax({
            url: ffpVisualization.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ffp_get_chart_data',
                nonce: ffpVisualization.nonce,
                chart_id: chartId,
                period: params.period || 'last_30_days',
                form_id: params.form_id || 0
            }
        }).then(function(response) {
            if (response.success && response.data) {
                return response.data.data;
            }
            return [];
        });
    };

    FFPVisualization.refresh = function(chartId, params) {
        FFPVisualization.loadData(chartId, params).then(function(data) {
            if (window.FFPCharts[chartId]) {
                window.FFPCharts[chartId].config.data = data;
                window.FFPCharts[chartId].rendered = false;
                FFPVisualization.render(chartId);
            }
        });
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        FFPVisualization.init();
    });

})(jQuery, d3);
