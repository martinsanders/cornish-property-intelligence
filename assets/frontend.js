(function () {
    'use strict';

    const activeClassSuffix = '-control-chip--active';

    function text(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function escapeHtml(value) {
        return text(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function number(value) {
        const parsed = Number(value);

        return Number.isFinite(parsed) ? parsed : null;
    }

    function formatNumber(value) {
        const parsed = number(value);

        return parsed === null
            ? escapeHtml(value)
            : new Intl.NumberFormat('en-GB', { maximumFractionDigits: parsed % 1 === 0 ? 0 : 1 }).format(parsed);
    }

    function sumValues(values) {
        if (!Array.isArray(values)) {
            return null;
        }

        const total = values.reduce((sum, value) => {
            const parsed = number(value);

            return parsed === null ? sum : sum + parsed;
        }, 0);

        return Number.isFinite(total) ? total : null;
    }

    function topItemLabel(items) {
        if (!Array.isArray(items) || !items.length) {
            return '';
        }

        const top = items.reduce((best, item) => {
            const value = number(item?.value);

            if (value === null) {
                return best;
            }

            if (!best || value > best.value) {
                return {
                    label: text(item.label || item.name || item.code),
                    value,
                };
            }

            return best;
        }, null);

        return top?.label || '';
    }

    function readPayload(chart) {
        if (chart.__cpiPayload) {
            return clone(chart.__cpiPayload);
        }

        const script = chart.querySelector('[data-cpi-chart-payload]');

        if (!script) {
            return {};
        }

        try {
            chart.__cpiPayload = JSON.parse(script.textContent || '{}');
            return clone(chart.__cpiPayload);
        } catch (error) {
            return {};
        }
    }

    function clone(payload) {
        return JSON.parse(JSON.stringify(payload || {}));
    }

    function selectedControls(module) {
        const controls = {};

        module.querySelectorAll('[data-cpi-control-group]').forEach((group) => {
            const key = group.dataset.cpiControlGroup;
            const select = group.querySelector('[data-cpi-control-select]');
            const active = group.querySelector('[data-cpi-control-option][aria-pressed="true"]');

            if (key && select) {
                controls[key] = select.value;
            } else if (key && active) {
                controls[key] = active.dataset.cpiControlOption || active.textContent.trim();
            }
        });

        return controls;
    }

    function selectedControlLabels(module) {
        const labels = [];

        module.querySelectorAll('[data-cpi-control-group]').forEach((group) => {
            const select = group.querySelector('[data-cpi-control-select]');
            const active = group.querySelector('[data-cpi-control-option][aria-pressed="true"]');

            if (select) {
                const selected = select.options[select.selectedIndex];

                if (selected?.textContent.trim()) {
                    labels.push(selected.textContent.trim());
                }
            } else if (active?.textContent.trim()) {
                labels.push(active.textContent.trim());
            }
        });

        return labels;
    }

    function periodLimit(label) {
        const normalised = text(label).toLowerCase();

        if (normalised.includes('month') && !normalised.includes('3') && !normalised.includes('6') && !normalised.includes('12')) {
            return 1;
        }

        if (normalised.includes('3')) {
            return 3;
        }

        if (normalised.includes('6')) {
            return 6;
        }

        if (normalised.includes('12') || normalised.includes('year')) {
            return 12;
        }

        if (normalised.includes('5')) {
            return 60;
        }

        return null;
    }

    function slicePayload(payload, limit) {
        if (!limit || !Array.isArray(payload.categories)) {
            return payload;
        }

        const start = Math.max(payload.categories.length - limit, 0);

        return {
            ...payload,
            categories: payload.categories.slice(start),
            month_keys: Array.isArray(payload.month_keys) ? payload.month_keys.slice(start) : payload.month_keys,
            series: Array.isArray(payload.series)
                ? payload.series.map((series) => ({
                    ...series,
                    data: Array.isArray(series.data) ? series.data.slice(start) : series.data,
                    values: Array.isArray(series.values) ? series.values.slice(start) : series.values,
                }))
                : payload.series,
        };
    }

    function filterSourceSeries(payload, sourceFocus) {
        if (!sourceFocus || sourceFocus === 'All sources' || !Array.isArray(payload.series)) {
            return payload;
        }

        return {
            ...payload,
            series: payload.series.filter((series) => series.name === sourceFocus),
        };
    }

    function filterMonthlyComparison(payload, controls) {
        let next = payload;

        if (controls.property_type && controls.property_type !== 'All property types' && payload.property_type_comparisons?.[controls.property_type]) {
            next = {
                ...payload.property_type_comparisons[controls.property_type],
                property_type_comparisons: payload.property_type_comparisons,
            };
        }

        next = slicePayload(next, periodLimit(controls.period));

        if (controls.metric_view === 'Sales volume only' && Array.isArray(next.series)) {
            next = {
                ...next,
                series: next.series.filter((series) => !text(series.name).toLowerCase().includes('price')),
            };
        }

        return next;
    }

    function filterSourceComparison(payload, controls) {
        let next = payload;

        if (controls.trade_focus && controls.trade_focus !== 'All trades' && payload.trade_source_comparisons?.[controls.trade_focus]) {
            next = {
                ...payload.trade_source_comparisons[controls.trade_focus],
                trade_source_comparisons: payload.trade_source_comparisons,
            };
        } else if (controls.trade_focus && controls.trade_focus !== 'All trades') {
            next = {
                ...payload,
                categories: [],
                month_keys: [],
                series: [],
                emptyText: `No monthly source trend is available for ${controls.trade_focus} yet.`,
            };
        }

        next = slicePayload(next, periodLimit(controls.period));
        next = filterSourceSeries(next, controls.source_focus);

        return next;
    }

    function periodItemsKey(value) {
        const key = text(value).toLowerCase().replace(/\s+/g, '_');
        const aliases = {
            'past_month': 'last_month',
            'latest_month': 'latest_month',
            'past_3_months': 'last_3_months',
            'past_6_months': 'last_6_months',
            'past_12_months': 'last_12_months',
            'past_year': 'last_year',
            'all_time': 'all_time',
        };

        return aliases[key] || key;
    }

    function filterDistribution(payload, controls) {
        let next = payload;
        const periodKey = periodItemsKey(controls.time_span || controls.period);
        const sourceFocus = controls.source_focus || 'All sources';

        if (payload.trade_source_comparisons && Object.keys(payload.trade_source_comparisons).length) {
            const limit = periodLimit(controls.period);
            const items = Object.entries(payload.trade_source_comparisons)
                .map(([label, comparison]) => {
                    let comparisonPayload = clone(comparison);
                    comparisonPayload = slicePayload(comparisonPayload, limit);
                    comparisonPayload = filterSourceSeries(comparisonPayload, sourceFocus);

                    return {
                        label,
                        value: (comparisonPayload.series || []).reduce((total, series) => total + (sumValues(series.data || series.values) || 0), 0),
                    };
                })
                .filter((item) => item.value > 0)
                .sort((a, b) => a.label.localeCompare(b.label, 'en-GB', { sensitivity: 'base' }));

            if (items.length) {
                next = {
                    ...next,
                    items,
                };
            }
        } else if (periodKey && payload.period_items?.[periodKey]) {
            next = {
                ...next,
                items: payload.period_items[periodKey],
            };
        }

        if (controls.trade_focus && controls.trade_focus !== 'All trades' && Array.isArray(next.items)) {
            next = {
                ...next,
                items: next.items.filter((item) => item.label === controls.trade_focus),
            };
        }

        if (controls.property_type && controls.property_type !== 'All property types' && Array.isArray(next.items)) {
            next = {
                ...next,
                items: next.items.filter((item) => item.label === controls.property_type),
            };
        }

        return next;
    }

    function filterRatingComparison(payload, controls) {
        let next = payload;

        if (controls.epc_view && controls.epc_view !== 'Current vs potential' && Array.isArray(next.series)) {
            const needle = controls.epc_view === 'Current rating distribution'
                ? 'Current EPC rating'
                : 'Potential EPC rating';

            next = {
                ...next,
                series: next.series.filter((series) => series.name === needle),
            };
        }

        return next;
    }

    function bucketsFromSeries(payload) {
        const labels = Array.isArray(payload.categories) ? payload.categories : [];
        const series = Array.isArray(payload.series) ? payload.series : [];

        return series.map((item) => ({
            name: item.name || 'Series',
            buckets: labels.reduce((buckets, label, index) => {
                const values = Array.isArray(item.data) ? item.data : item.values;
                const value = Array.isArray(values) ? number(values[index]) : null;

                if (value !== null) {
                    buckets[label] = value;
                }

                return buckets;
            }, {}),
        })).filter((item) => Object.keys(item.buckets).length > 0);
    }

    function bucketsFromItems(payload) {
        const items = Array.isArray(payload.items) ? payload.items : [];
        const buckets = {};

        items.forEach((item) => {
            const label = text(item.label || item.name || item.code);
            const value = number(item.value);

            if (label && value !== null) {
                buckets[label] = value;
            }
        });

        return [{ name: payload.seriesName || 'Series', buckets }].filter((item) => Object.keys(item.buckets).length > 0);
    }

    function renderBars(buckets, prefix) {
        const values = Object.values(buckets).map(number).filter((value) => value !== null);
        const max = values.length ? Math.max(...values) : 0;

        if (max <= 0) {
            return '';
        }

        return `<div class="${prefix}-bars" role="list">${
            Object.entries(buckets).map(([label, value]) => {
                const parsed = number(value) || 0;
                const width = parsed > 0 ? Math.max(4, Math.min(100, Math.round((parsed / max) * 100))) : 0;

                return `<div class="${prefix}-bar" role="listitem">
                    <div class="${prefix}-bar__label-row"><span>${escapeHtml(label)}</span><span>${formatNumber(parsed)}</span></div>
                    <div class="${prefix}-bar__track" aria-hidden="true"><span class="${prefix}-bar__fill" style="width: ${width}%;"></span></div>
                </div>`;
            }).join('')
        }</div>`;
    }

    function renderSeries(series, prefix) {
        if (!series.length) {
            return '';
        }

        return series.map((item) => `<div class="${prefix}-chart__series">
            <p class="${prefix}-chart__series-name">${escapeHtml(item.name)}</p>
            ${renderBars(item.buckets, prefix)}
        </div>`).join('');
    }

    function renderEmptyState(message, prefix) {
        const textValue = text(message || 'No chart data is available for this view yet.');

        return `<p class="${prefix}-chart__empty">${escapeHtml(textValue)}</p>`;
    }

    function prefixFor(chart) {
        return chart.className.includes('cpi-postcode-area') ? 'cpi-postcode-area' : 'cpi-location';
    }

    function chartPalette() {
        const source = document.querySelector('.cpi-virtual-page, .cpi-location-modules, .cpi-postcode-area-modules') || document.documentElement;
        const styles = getComputedStyle(source);
        const primary = styles.getPropertyValue('--cpi-color-primary').trim() || '#245a70';
        const accent = styles.getPropertyValue('--cpi-color-accent').trim() || '#9ec7bd';
        const warning = styles.getPropertyValue('--cpi-color-warning').trim() || '#c7973e';
        const muted = styles.getPropertyValue('--cpi-color-muted').trim() || '#64748b';

        return [primary, accent, warning, muted, '#d6674f'];
    }

    function themeValue(variable, fallback) {
        const source = document.querySelector('.cpi-virtual-page, .cpi-location-modules, .cpi-postcode-area-modules') || document.documentElement;
        const value = getComputedStyle(source).getPropertyValue(variable).trim();

        return value || fallback;
    }

    function filteredPayload(chart, controls) {
        const type = chart.dataset.cpiInteractiveChart;
        const payload = readPayload(chart);

        if (type === 'monthly-comparison') {
            return filterMonthlyComparison(payload, controls);
        }

        if (type === 'source-comparison') {
            return filterSourceComparison(payload, controls);
        }

        if (type === 'rating-comparison') {
            return filterRatingComparison(payload, controls);
        }

        if (type === 'distribution') {
            return filterDistribution(payload, controls);
        }

        return payload;
    }

    function updateSummary(module, controls) {
        const summary = module.querySelector('[class$="-data-studio__summary"]');

        if (!summary) {
            return;
        }

        const selected = selectedControlLabels(module);

        if (selected.length) {
            summary.textContent = `Current view: ${selected.join(' · ')}`;
        }
    }

    function chartStatusText(chart, payload, controls) {
        const type = chart.dataset.cpiInteractiveChart;
        const parts = [];

        if ((type === 'source-comparison' || type === 'monthly-comparison') && controls.period) {
            parts.push(controls.period);
        }

        if (type === 'source-comparison' && controls.source_focus) {
            parts.push(controls.source_focus);
        }

        if (
            type === 'source-comparison'
            && controls.trade_focus
            && controls.trade_focus !== 'All trades'
            && payload.trade_source_comparisons?.[controls.trade_focus]
        ) {
            parts.push(controls.trade_focus);
        }

        if (type === 'distribution') {
            const periodKey = periodItemsKey(controls.time_span || controls.period);

            if (periodKey && payload.period_items?.[periodKey]) {
                parts.push(controls.time_span || controls.period);
            }

            if (controls.trade_focus) {
                parts.push(controls.trade_focus);
            }

            if (controls.property_type && controls.property_type !== 'All property types') {
                parts.push(controls.property_type);
            }
        }

        if (type === 'rating-comparison' && controls.epc_view) {
            parts.push(controls.epc_view);
        }

        return parts.length ? `Chart view: ${parts.join(' · ')}` : '';
    }

    function updateChartStatus(chart, payload, controls) {
        const status = chart.querySelector('[data-cpi-chart-status]');

        if (!status) {
            return;
        }

        const label = chartStatusText(chart, payload, controls);
        status.textContent = label;
        status.hidden = label === '';
    }

    function chartByType(module, type) {
        return module.querySelector(`[data-cpi-interactive-chart="${type}"]`);
    }

    function updateText(target, value) {
        if (!target || value === null || value === undefined || value === '') {
            return;
        }

        target.textContent = formatNumber(value);
    }

    function updateTradeSupportingEvidence(module, controls) {
        const sourceChart = chartByType(module, 'source-comparison');
        const distributionChart = chartByType(module, 'distribution');
        const sourcePayload = sourceChart ? filteredPayload(sourceChart, controls) : {};
        const distributionPayload = distributionChart ? filteredPayload(distributionChart, controls) : {};
        const sourceFocus = controls.source_focus || 'All sources';
        const tradeFocus = controls.trade_focus || 'All trades';
        const distributionItems = Array.isArray(distributionPayload.items) ? distributionPayload.items : [];

        if (tradeFocus !== 'All trades') {
            const selected = distributionItems.find((item) => text(item.label || item.name || item.code) === tradeFocus);
            updateText(module.querySelector('[data-cpi-trade-support-high-signal]'), selected?.value);
        } else if (sourceFocus !== 'All sources' && Array.isArray(sourcePayload.series)) {
            const selectedSource = sourcePayload.series.find((series) => text(series.name) === sourceFocus);
            updateText(module.querySelector('[data-cpi-trade-support-high-signal]'), sumValues(selectedSource?.data || selectedSource?.values));
        }

        const leadingTrade = topItemLabel(distributionItems);
        const leadingTradeTarget = module.querySelector('[data-cpi-trade-support-leading-trade]');

        if (leadingTradeTarget && leadingTrade) {
            leadingTradeTarget.textContent = leadingTrade;
        }

        module.querySelectorAll('[data-cpi-trade-support-source-count]').forEach((target) => {
            const sourceName = target.dataset.cpiTradeSupportSourceCount;
            const series = Array.isArray(sourcePayload.series)
                ? sourcePayload.series.find((item) => text(item.name) === sourceName)
                : null;
            const total = sumValues(series?.data || series?.values);

            target.textContent = total === null ? 'N/A' : formatNumber(total);
        });
    }

    function updateModule(module) {
        const controls = selectedControls(module);

        updateSummary(module, controls);
        updateTradeSupportingEvidence(module, controls);

        module.querySelectorAll('[data-cpi-interactive-chart]').forEach((chart) => {
            const output = chart.querySelector('[data-cpi-chart-output]');
            const payload = filteredPayload(chart, controls);
            const prefix = prefixFor(chart);
            const series = chart.dataset.cpiInteractiveChart === 'distribution'
                ? bucketsFromItems(payload)
                : bucketsFromSeries(payload);
            const html = renderSeries(series, prefix);

            updateChartStatus(chart, payload, controls);

            if (output) {
                output.innerHTML = html || renderEmptyState(payload.emptyText, prefix);
            }

            updateEchart(chart, payload);
        });
    }

    function validSeriesPayload(payload) {
        return Array.isArray(payload.categories)
            && payload.categories.length > 0
            && Array.isArray(payload.series)
            && payload.series.length > 0;
    }

    function validDistributionPayload(payload) {
        return Array.isArray(payload.items)
            && payload.items.some((item) => item && number(item.value) !== null && text(item.label || item.name || item.code) !== '');
    }

    function numericSeriesData(series) {
        const values = Array.isArray(series.data) ? series.data : series.values;

        return Array.isArray(values)
            ? values.map((value) => {
                const parsed = number(value);
                return parsed === null ? null : parsed;
            })
            : [];
    }

    function baseEchartOption() {
        const ink = themeValue('--cpi-color-ink', '#061126');
        const border = themeValue('--cpi-color-border', 'rgba(100,116,139,0.22)');

        return {
            color: chartPalette(),
            textStyle: {
                color: ink,
                fontFamily: 'inherit',
            },
            tooltip: {
                trigger: 'axis',
                confine: true,
                backgroundColor: '#fff',
                borderColor: border,
                borderWidth: 1,
                padding: [9, 11],
                textStyle: {
                    color: ink,
                    fontSize: 13,
                    fontWeight: 600,
                },
                extraCssText: 'box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12); border-radius: 6px;',
            },
            grid: {
                left: 8,
                right: 16,
                top: 42,
                bottom: 8,
                containLabel: true,
            },
        };
    }

    function distributionOption(payload) {
        const items = (payload.items || [])
            .map((item) => ({
                label: text(item.label || item.name || item.code),
                value: number(item.value),
            }))
            .filter((item) => item.label && item.value !== null);

        if (!items.length) {
            return null;
        }

        const ink = themeValue('--cpi-color-ink', '#061126');
        const grid = themeValue('--cpi-chart-grid-color', 'rgba(100,116,139,0.18)');

        return {
            ...baseEchartOption(),
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                confine: true,
                backgroundColor: '#fff',
                borderColor: themeValue('--cpi-color-border', 'rgba(100,116,139,0.22)'),
                borderWidth: 1,
                padding: [9, 11],
                textStyle: {
                    color: ink,
                    fontSize: 13,
                    fontWeight: 600,
                },
                extraCssText: 'box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12); border-radius: 6px;',
            },
            grid: {
                left: 8,
                right: 48,
                top: 18,
                bottom: 8,
                containLabel: true,
            },
            xAxis: {
                type: 'value',
                axisLine: { show: false },
                axisTick: { show: false },
                splitLine: { lineStyle: { color: grid } },
            },
            yAxis: {
                type: 'category',
                data: items.map((item) => item.label),
                inverse: true,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: { color: ink, fontWeight: 700 },
            },
            series: [{
                name: payload.seriesName || 'Count',
                type: 'bar',
                data: items.map((item) => item.value),
                barMaxWidth: 22,
                itemStyle: { borderRadius: [0, 8, 8, 0] },
                label: {
                    show: true,
                    position: 'right',
                    color: ink,
                    fontWeight: 700,
                    formatter: ({ value }) => formatNumber(value),
                },
            }],
        };
    }

    function seriesOption(payload, chartType) {
        if (!validSeriesPayload(payload)) {
            return null;
        }

        const isRating = chartType === 'rating-comparison';
        const isSource = chartType === 'source-comparison';
        const usesMedianAxis = chartType === 'monthly-comparison'
            && payload.series.some((series) => text(series.name).toLowerCase().includes('price'));
        const ink = themeValue('--cpi-color-ink', '#061126');
        const grid = themeValue('--cpi-chart-grid-color', 'rgba(100,116,139,0.18)');
        const axis = themeValue('--cpi-color-border', 'rgba(100,116,139,0.3)');

        return {
            ...baseEchartOption(),
            legend: {
                top: 0,
                right: 0,
                itemGap: 14,
                itemHeight: 10,
                itemWidth: 20,
                textStyle: { color: ink, fontSize: 12, fontWeight: 700 },
            },
            xAxis: {
                type: 'category',
                data: payload.categories,
                boundaryGap: isRating,
                axisLine: { lineStyle: { color: axis } },
                axisTick: { show: false },
                axisLabel: {
                    color: ink,
                    interval: 0,
                    rotate: payload.categories.length > 6 ? 22 : 0,
                },
            },
            yAxis: usesMedianAxis ? [
                {
                    type: 'value',
                    name: 'Count',
                    axisLine: { show: false },
                    axisTick: { show: false },
                    splitLine: { lineStyle: { color: grid } },
                },
                {
                    type: 'value',
                    name: 'Price',
                    axisLine: { show: false },
                    axisTick: { show: false },
                    splitLine: { show: false },
                    axisLabel: { formatter: (value) => `£${new Intl.NumberFormat('en-GB', { notation: 'compact' }).format(value)}` },
                },
            ] : {
                type: 'value',
                axisLine: { show: false },
                axisTick: { show: false },
                splitLine: { lineStyle: { color: grid } },
            },
            series: payload.series.map((series) => {
                const name = text(series.name || 'Series');
                const isPrice = name.toLowerCase().includes('price');
                const type = isRating ? 'bar' : (chartType === 'monthly-comparison' ? 'line' : (series.type === 'bar' ? 'bar' : 'line'));

                return {
                    name,
                    type: isSource ? 'line' : type,
                    data: numericSeriesData(series),
                    yAxisIndex: usesMedianAxis && isPrice ? 1 : 0,
                    smooth: type === 'line',
                    symbolSize: type === 'line' ? 8 : undefined,
                    barMaxWidth: type === 'bar' ? 28 : undefined,
                    itemStyle: type === 'bar' ? { borderRadius: [8, 8, 0, 0] } : undefined,
                    lineStyle: type === 'line' ? { width: 3 } : undefined,
                    areaStyle: chartType === 'monthly-comparison' && type === 'line' && !isPrice ? { opacity: 0.14 } : undefined,
                    connectNulls: false,
                };
            }),
        };
    }

    function echartOption(chart, payload) {
        const type = chart.dataset.cpiInteractiveChart;

        if (type === 'distribution') {
            return validDistributionPayload(payload) ? distributionOption(payload) : null;
        }

        if (type === 'monthly-comparison' || type === 'source-comparison' || type === 'rating-comparison') {
            return seriesOption(payload, type);
        }

        return null;
    }

    function updateEchart(chart, payload) {
        const target = chart.querySelector('[data-cpi-echart]');

        if (!target || !window.echarts) {
            return;
        }

        const option = echartOption(chart, payload);

        if (!option) {
            chart.classList.remove(`${prefixFor(chart)}-chart--echarts-ready`);
            target.style.display = '';
            target.setAttribute('aria-hidden', 'true');

            if (chart.__cpiEchart) {
                chart.__cpiEchart.clear();
            }

            return;
        }

        try {
            target.style.display = 'block';
            const instance = chart.__cpiEchart || window.echarts.init(target, null, { renderer: 'svg' });
            chart.__cpiEchart = instance;
            instance.setOption(option, true);
            chart.classList.add(`${prefixFor(chart)}-chart--echarts-ready`);
            target.setAttribute('aria-hidden', 'false');
            instance.resize();
        } catch (error) {
            chart.classList.remove(`${prefixFor(chart)}-chart--echarts-ready`);
            target.style.display = '';
            target.setAttribute('aria-hidden', 'true');
        }
    }

    function resizeCharts(root) {
        root.querySelectorAll('[data-cpi-interactive-chart]').forEach((chart) => {
            if (chart.__cpiEchart) {
                chart.__cpiEchart.resize();
            }
        });
    }

    function activateButton(button) {
        const group = button.closest('[data-cpi-control-group]');

        if (!group || group.dataset.cpiControlMode !== 'interactive') {
            return;
        }

        group.querySelectorAll('[data-cpi-control-option]').forEach((item) => {
            const baseClass = Array.from(item.classList).find((className) => className.endsWith('-control-chip'));
            const activeClass = baseClass ? `${baseClass}--active` : Array.from(item.classList).find((className) => className.endsWith(activeClassSuffix));
            item.setAttribute('aria-pressed', item === button ? 'true' : 'false');

            if (activeClass) {
                item.classList.toggle(activeClass, item === button);
            }
        });

        const module = button.closest('[data-cpi-module-root]');

        if (module) {
            updateModule(module);
        }
    }

    function activateSelect(select) {
        const group = select.closest('[data-cpi-control-group]');

        if (!group || group.dataset.cpiControlMode !== 'interactive') {
            return;
        }

        syncSelectWrapper(select);

        const module = select.closest('[data-cpi-module-root]');

        if (module) {
            updateModule(module);
        }
    }

    function closeSelects(root, except) {
        root.querySelectorAll('[data-cpi-select-wrapper].cpi-select--open').forEach((wrapper) => {
            if (wrapper === except) {
                return;
            }

            wrapper.classList.remove('cpi-select--open');
            const button = wrapper.querySelector('[data-cpi-select-button]');

            if (button) {
                button.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function toggleSelect(button) {
        const wrapper = button.closest('[data-cpi-select-wrapper]');

        if (!wrapper || wrapper.classList.contains('cpi-location-select--readonly') || wrapper.classList.contains('cpi-postcode-area-select--readonly')) {
            return;
        }

        const willOpen = !wrapper.classList.contains('cpi-select--open');
        closeSelects(document, wrapper);
        wrapper.classList.toggle('cpi-select--open', willOpen);
        button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    }

    function chooseSelectOption(option) {
        const wrapper = option.closest('[data-cpi-select-wrapper]');
        const select = wrapper?.querySelector('[data-cpi-control-select]');

        if (!wrapper || !select) {
            return;
        }

        select.value = option.value || option.dataset.cpiSelectValue || option.textContent.trim();
        syncSelectWrapper(select);
        wrapper.classList.remove('cpi-select--open');
        const button = wrapper.querySelector('[data-cpi-select-button]');

        if (button) {
            button.setAttribute('aria-expanded', 'false');
        }

        activateSelect(select);
    }

    function syncSelectWrapper(select) {
        const wrapper = select.closest('[data-cpi-select-wrapper]');

        if (!wrapper) {
            return;
        }

        const selected = select.options[select.selectedIndex];
        const label = wrapper.querySelector('[data-cpi-select-label]');

        if (label && selected) {
            label.textContent = selected.textContent;
        }

        wrapper.querySelectorAll('[data-cpi-select-option]').forEach((option) => {
            const selectedValue = selected ? selected.value : select.value;
            const isSelected = option.value === selectedValue || option.dataset.cpiSelectValue === selectedValue;
            option.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        });
    }

    function resetModuleControls(button) {
        const module = button.closest('[data-cpi-module-root]');

        if (!module) {
            return;
        }

        module.querySelectorAll('[data-cpi-control-select]').forEach((select) => {
            const activeOption = Array.from(select.options).find((option) => option.defaultSelected);

            if (activeOption) {
                select.value = activeOption.value;
            } else if (select.options.length) {
                select.selectedIndex = 0;
            }

            syncSelectWrapper(select);
        });

        updateModule(module);
    }

    function init(root) {
        root.querySelectorAll('[data-cpi-module-root]').forEach(updateModule);

        root.addEventListener('click', (event) => {
            const selectOption = event.target.closest('[data-cpi-select-option]');

            if (selectOption) {
                chooseSelectOption(selectOption);
                return;
            }

            const selectButton = event.target.closest('[data-cpi-select-button]');

            if (selectButton) {
                toggleSelect(selectButton);
                return;
            }

            closeSelects(root);

            const reset = event.target.closest('[data-cpi-reset-controls]');

            if (reset) {
                resetModuleControls(reset);
                return;
            }

            const button = event.target.closest('[data-cpi-control-option]');

            if (button) {
                activateButton(button);
            }
        });

        root.addEventListener('change', (event) => {
            const select = event.target.closest('[data-cpi-control-select]');

            if (select) {
                activateSelect(select);
            }
        });

        root.querySelectorAll('[data-cpi-control-select]').forEach(syncSelectWrapper);
        window.addEventListener('resize', () => resizeCharts(root));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => init(document));
    } else {
        init(document);
    }
}());
