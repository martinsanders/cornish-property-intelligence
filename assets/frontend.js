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

    function isObject(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }

    function selectedControls(module, includeInactive = false) {
        const controls = {};

        module.querySelectorAll('[data-cpi-control-group]').forEach((group) => {
            if (!includeInactive && !controlGroupApplies(module, group)) {
                return;
            }

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

    function moduleControlParam(module, key) {
        const moduleKey = text(module?.dataset?.cpiModuleKey || 'module')
            .toLowerCase()
            .replace(/[^a-z0-9_]+/g, '_')
            .replace(/^_+|_+$/g, '');
        const controlKey = text(key)
            .toLowerCase()
            .replace(/[^a-z0-9_]+/g, '_')
            .replace(/^_+|_+$/g, '');

        if (!moduleKey || !controlKey) {
            return '';
        }

        return `cpi_${moduleKey}_${controlKey}`;
    }

    function defaultSelectValue(select) {
        const defaultOption = Array.from(select.options).find((option) => option.defaultSelected);

        return defaultOption?.value || select.options[0]?.value || '';
    }

    function defaultButtonValue(group) {
        const active = group.querySelector('[data-cpi-control-option][aria-pressed="true"]');
        const first = group.querySelector('[data-cpi-control-option]');

        return active?.dataset.cpiControlOption || active?.textContent.trim() || first?.dataset.cpiControlOption || first?.textContent.trim() || '';
    }

    function defaultControlValue(group) {
        const select = group.querySelector('[data-cpi-control-select]');

        if (select) {
            return defaultSelectValue(select);
        }

        return defaultButtonValue(group);
    }

    function updateUrlForModule(module) {
        if (!window.history?.replaceState) {
            return;
        }

        const url = new URL(window.location.href);

        if (module.dataset.cpiModuleKey === 'epc_status') {
            url.searchParams.delete(moduleControlParam(module, 'epc_view'));
        }

        module.querySelectorAll('[data-cpi-control-group]').forEach((group) => {
            const key = group.dataset.cpiControlGroup;
            const param = moduleControlParam(module, key);

            if (!param) {
                return;
            }

            if (!controlGroupApplies(module, group)) {
                url.searchParams.delete(param);
                return;
            }

            const select = group.querySelector('[data-cpi-control-select]');
            const active = group.querySelector('[data-cpi-control-option][aria-pressed="true"]');
            const value = select
                ? select.value
                : (active?.dataset.cpiControlOption || active?.textContent.trim() || '');
            const defaultValue = defaultControlValue(group);

            if (value && value !== defaultValue) {
                url.searchParams.set(param, value);
            } else {
                url.searchParams.delete(param);
            }
        });

        const next = `${url.pathname}${url.search}${url.hash}`;
        const current = `${window.location.pathname}${window.location.search}${window.location.hash}`;

        if (next !== current) {
            window.history.replaceState({}, '', next);
        }
    }

    function applyUrlControls(module) {
        const params = new URLSearchParams(window.location.search);

        module.querySelectorAll('[data-cpi-control-group]').forEach((group) => {
            const key = group.dataset.cpiControlGroup;
            const param = moduleControlParam(module, key);
            const value = param ? params.get(param) : null;

            if (!value) {
                return;
            }

            const select = group.querySelector('[data-cpi-control-select]');

            if (select && Array.from(select.options).some((option) => option.value === value)) {
                select.value = value;
                syncSelectWrapper(select);
                return;
            }

            const button = Array.from(group.querySelectorAll('[data-cpi-control-option]')).find((option) => {
                return option.dataset.cpiControlOption === value || option.textContent.trim() === value;
            });

            if (button) {
                group.querySelectorAll('[data-cpi-control-option]').forEach((option) => {
                    option.setAttribute('aria-pressed', option === button ? 'true' : 'false');
                });
            }
        });
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

    function epcTimelineLimit(value) {
        const normalised = text(value).toLowerCase();

        if (normalised.includes('all')) {
            return null;
        }

        if (normalised.includes('period') || normalised.includes('year') && !normalised.includes('3') && !normalised.includes('5') && !normalised.includes('10')) {
            return 1;
        }

        if (normalised.includes('3')) {
            return 3;
        }

        if (normalised.includes('10')) {
            return 10;
        }

        return 5;
    }

    function slicePayload(payload, limit) {
        const labels = seriesLabels(payload);

        if (!limit || labels.length === 0) {
            return payload;
        }

        const start = Math.max(labels.length - limit, 0);

        return {
            ...payload,
            categories: Array.isArray(payload.categories) ? payload.categories.slice(start) : payload.categories,
            labels: Array.isArray(payload.labels) ? payload.labels.slice(start) : payload.labels,
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

    function seriesLabels(payload) {
        if (Array.isArray(payload.categories)) {
            return payload.categories;
        }

        if (Array.isArray(payload.labels)) {
            return payload.labels;
        }

        return [];
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

        if (controls.compare_with === 'Previous period') {
            next = previousPeriodComparisonPayload(next, periodLimit(controls.period));
        } else {
            next = slicePayload(next, periodLimit(controls.period));
        }

        next = normalizeMonthlyComparisonSeries(next, controls);

        if (controls.metric_view === 'Sales volume only' && Array.isArray(next.series)) {
            next = {
                ...next,
                series: next.series.filter((series) => !text(series.name).toLowerCase().includes('price')),
            };
        }

        if (controls.compare_with === 'None' && Array.isArray(next.series)) {
            next = {
                ...next,
                series: next.series.filter((series) => {
                    const name = text(series.name).toLowerCase();

                    return !name.includes('previous') && !name.includes('comparison');
                }),
            };
        }

        return next;
    }

    function isComparisonSeries(series) {
        const name = text(series?.name).toLowerCase();

        return name.includes('previous') || name.includes('comparison') || name.includes('same period');
    }

    function normalizeMonthlyComparisonSeries(payload, controls) {
        if (!Array.isArray(payload.series)) {
            return payload;
        }

        const keepComparison = controls.compare_with && controls.compare_with !== 'None';
        const series = payload.series
            .filter((series) => keepComparison || !isComparisonSeries(series))
            .map((series) => {
                const name = text(series.name || 'Series');
                const lower = name.toLowerCase();
                const isPrice = lower.includes('price');
                const values = Array.isArray(series.data) ? series.data : series.values;
                const normalizedName = isPrice
                    ? (isComparisonSeries(series) ? name : 'Median price')
                    : (isComparisonSeries(series) ? name : 'Sales');
                const normalizedValues = isPrice && Array.isArray(values)
                    ? values.map((value) => {
                        const parsed = number(value);
                        return parsed === 0 ? null : value;
                    })
                    : values;

                return {
                    ...series,
                    name: normalizedName,
                    data: Array.isArray(series.data) ? normalizedValues : series.data,
                    values: Array.isArray(series.data) ? series.values : normalizedValues,
                };
            });

        if (
            controls.compare_with === 'Same period last year'
            && !series.some((series) => text(series.name).toLowerCase().includes('same period'))
            && Array.isArray(payload.categories)
            && payload.categories.length > 0
        ) {
            const comparisonSales = number(payload.same_month_last_year_count);
            const comparisonPrice = number(payload.same_month_last_year_median_price);
            const lastPoint = payload.categories.length - 1;
            const pointSeries = [];

            if (comparisonSales !== null) {
                pointSeries.push({
                    name: 'Same period last year',
                    data: payload.categories.map((category, index) => (index === lastPoint ? comparisonSales : null)),
                });
            }

            if (comparisonPrice !== null) {
                pointSeries.push({
                    name: 'Same period last year median price',
                    data: payload.categories.map((category, index) => (index === lastPoint ? comparisonPrice : null)),
                });
            }

            series.push(...pointSeries);
        }

        return {
            ...payload,
            series,
        };
    }

    function previousPeriodComparisonPayload(payload, limit) {
        const sliced = slicePayload(payload, limit);
        const monthKeys = Array.isArray(payload.month_keys) ? payload.month_keys : [];
        const currentKeys = Array.isArray(sliced.month_keys) ? sliced.month_keys : [];
        const currentSeries = Array.isArray(sliced.series)
            ? sliced.series.filter((series) => !isComparisonSeries(series))
            : [];

        if (!monthKeys.length || !currentKeys.length || !currentSeries.length) {
            return {
                ...sliced,
                series: currentSeries,
            };
        }

        const currentStart = Math.max(0, monthKeys.length - currentKeys.length);
        const previousStart = Math.max(0, currentStart - currentKeys.length);
        const previousKeys = monthKeys.slice(previousStart, currentStart);

        if (!previousKeys.length) {
            return {
                ...sliced,
                series: currentSeries,
            };
        }

        const valueByMonth = isObject(payload.primary_by_month)
            ? payload.primary_by_month
            : monthKeys.reduce((values, key, index) => {
                const sourceValues = Array.isArray(payload.series?.[0]?.data)
                    ? payload.series[0].data
                    : payload.series?.[0]?.values;

                if (Array.isArray(sourceValues)) {
                    values[key] = sourceValues[index];
                }

                return values;
            }, {});
        const offset = currentKeys.length - previousKeys.length;
        const previousValues = currentKeys.map((key, index) => {
            const previousKey = previousKeys[index - offset];
            const value = previousKey ? number(valueByMonth[previousKey]) : null;

            return value === null ? null : value;
        });

        return {
            ...sliced,
            series: [
                ...currentSeries,
                {
                    name: 'Previous period sales',
                    data: previousValues,
                },
            ],
        };
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

        return next;
    }

    function filterEpcTimeSeries(payload, chart) {
        if (!Array.isArray(payload.series)) {
            return payload;
        }

        const selectedMetric = text(chart.dataset.cpiEpcDefaultMetric || 'record_count');
        const selectedSeries = payload.series.find((series) => text(series.metric) === selectedMetric)
            || payload.series.find((series) => text(series.metric) === 'record_count')
            || payload.series[0];

        return {
            ...payload,
            series: selectedSeries
                ? [{
                    ...selectedSeries,
                    type: text(selectedSeries.metric) === 'record_count' ? 'bar' : 'line',
                    unit: text(selectedSeries.unit) || (text(selectedSeries.metric).includes('efficiency') || text(selectedSeries.metric).includes('gap') ? 'points' : ''),
                }]
                : [],
        };
    }

    function filterEpcTimelinePayload(payload, controls) {
        return slicePayload(payload, epcTimelineLimit(controls.epc_time_range));
    }

    function selectedTimeSlicePayloads(payload, controls) {
        if (controls.epc_time_range === 'all_periods') {
            return [];
        }

        const periods = Array.isArray(payload.time_slices?.periods) ? payload.time_slices.periods : [];
        const limit = epcTimelineLimit(controls.epc_time_range);
        const selected = limit ? periods.slice(-limit) : periods;

        return selected
            .map((period) => isObject(period?.payload) ? period.payload : null)
            .filter(Boolean);
    }

    function timeSlicedSeriesPayload(payload, controls) {
        const slices = selectedTimeSlicePayloads(payload, controls);

        if (!slices.length) {
            return payload;
        }

        const categories = [];
        const seriesNames = [];

        slices.forEach((slice) => {
            seriesLabels(slice).forEach((category) => {
                if (!categories.includes(category)) {
                    categories.push(category);
                }
            });
            (slice.series || []).forEach((series) => {
                const name = text(series.name || 'Series');

                if (name && !seriesNames.includes(name)) {
                    seriesNames.push(name);
                }
            });
        });

        if (!categories.length || !seriesNames.length) {
            return payload;
        }

        return {
            ...payload,
            categories,
            labels: undefined,
            series: seriesNames.map((name) => ({
                name,
                data: categories.map((category) => slices.reduce((total, slice) => {
                    const categoryIndex = seriesLabels(slice).indexOf(category);
                    const series = (slice.series || []).find((item) => text(item.name || 'Series') === name);
                    const value = categoryIndex >= 0 && Array.isArray(series?.data) ? number(series.data[categoryIndex]) : null;

                    return value === null ? total : total + value;
                }, 0)),
            })),
        };
    }

    function timeSlicedOpportunityPayload(payload, controls) {
        const slices = selectedTimeSlicePayloads(payload, controls);

        if (!slices.length) {
            return payload;
        }

        const categories = [];
        const seriesNames = [];
        const seriesUnits = {};

        slices.forEach((slice) => {
            seriesLabels(slice).forEach((category) => {
                if (!categories.includes(category)) {
                    categories.push(category);
                }
            });
            (slice.series || []).forEach((series) => {
                const name = text(series.name || 'Metric');

                if (name && !seriesNames.includes(name)) {
                    seriesNames.push(name);
                }

                if (name) {
                    seriesUnits[name] = text(series.unit || seriesUnits[name] || '');
                }
            });
        });

        const recordCounts = categories.map((category) => slices.reduce((total, slice) => {
            const index = seriesLabels(slice).indexOf(category);
            const count = index >= 0 && Array.isArray(slice.record_counts) ? number(slice.record_counts[index]) : null;

            return count === null ? total : total + count;
        }, 0));

        return {
            ...payload,
            categories,
            record_counts: recordCounts,
            series: seriesNames.map((name) => ({
                name,
                unit: seriesUnits[name],
                data: categories.map((category) => {
                    let weightedTotal = 0;
                    let weightTotal = 0;

                    slices.forEach((slice) => {
                        const categoryIndex = seriesLabels(slice).indexOf(category);
                        const series = (slice.series || []).find((item) => text(item.name || 'Metric') === name);
                        const value = categoryIndex >= 0 && Array.isArray(series?.data) ? number(series.data[categoryIndex]) : null;
                        const weight = categoryIndex >= 0 && Array.isArray(slice.record_counts) ? number(slice.record_counts[categoryIndex]) : null;

                        if (value === null || weight === null || weight <= 0) {
                            return;
                        }

                        weightedTotal += value * weight;
                        weightTotal += weight;
                    });

                    return weightTotal > 0 ? Math.round((weightedTotal / weightTotal) * 10) / 10 : null;
                }),
            })),
        };
    }

    function timeSlicedFuelPropertyMixPayload(payload, controls) {
        const slices = selectedTimeSlicePayloads(payload, controls);

        if (!slices.length) {
            return payload;
        }

        const categoryTotals = {};
        const fuelTotals = {};
        const cellCounts = {};

        slices.forEach((slice) => {
            const categories = seriesLabels(slice);

            categories.forEach((category, categoryIndex) => {
                const recordCount = Array.isArray(slice.record_counts) ? number(slice.record_counts[categoryIndex]) : null;

                categoryTotals[category] = (categoryTotals[category] || 0) + (recordCount || 0);
            });

            (slice.series || []).forEach((series) => {
                const fuel = text(series.name || 'Fuel');

                if (!fuel || !Array.isArray(series.data)) {
                    return;
                }

                series.data.forEach((point, categoryIndex) => {
                    const category = categories[categoryIndex];
                    const count = pointCount(point);

                    if (!category || count === null || count <= 0) {
                        return;
                    }

                    cellCounts[category] ??= {};
                    cellCounts[category][fuel] = (cellCounts[category][fuel] || 0) + count;
                    fuelTotals[fuel] = (fuelTotals[fuel] || 0) + count;
                });
            });
        });

        const categories = Object.keys(categoryTotals).filter((category) => categoryTotals[category] > 0);
        const fuels = Object.keys(fuelTotals).sort((a, b) => fuelTotals[b] - fuelTotals[a]);

        return {
            ...payload,
            categories,
            record_counts: categories.map((category) => categoryTotals[category]),
            series: fuels.map((fuel) => ({
                name: fuel,
                type: 'bar',
                stack: 'fuel',
                unit: 'percent',
                data: categories.map((category) => {
                    const count = cellCounts[category]?.[fuel] || 0;
                    const total = categoryTotals[category] || 0;

                    return {
                        value: total > 0 ? Math.round((count / total) * 1000) / 10 : 0,
                        count,
                    };
                }),
            })),
        };
    }

    function seriesByMetric(payload, metric) {
        return Array.isArray(payload.series)
            ? payload.series.find((series) => text(series.metric) === metric)
            : null;
    }

    function weightedSeriesAverage(payload, metric, weights) {
        const series = seriesByMetric(payload, metric);
        const values = Array.isArray(series?.data) ? series.data : series?.values;

        if (!Array.isArray(values) || !Array.isArray(weights)) {
            return null;
        }

        let weightedTotal = 0;
        let weightTotal = 0;

        values.forEach((value, index) => {
            const parsed = number(value);
            const weight = number(weights[index]);

            if (parsed === null || weight === null || weight <= 0) {
                return;
            }

            weightedTotal += parsed * weight;
            weightTotal += weight;
        });

        return weightTotal > 0 ? Math.round((weightedTotal / weightTotal) * 10) / 10 : null;
    }

    function epcTimelineSummary(module, controls) {
        const chart = module.querySelector('[data-cpi-interactive-chart="epc-time-series"]');
        const payload = chart ? filterEpcTimelinePayload(readPayload(chart), controls) : {};
        const recordSeries = seriesByMetric(payload, 'record_count');
        const records = Array.isArray(recordSeries?.data) ? recordSeries.data : recordSeries?.values;
        const recordCount = sumValues(records);

        if (recordCount === null) {
            return {};
        }

        return {
            record_count: recordCount,
            poor_rating_share: weightedSeriesAverage(payload, 'poor_rating_share', records),
            retrofit_signal_share: weightedSeriesAverage(payload, 'retrofit_signal_share', records),
            average_current_efficiency: weightedSeriesAverage(payload, 'average_current_efficiency', records),
            average_potential_efficiency: weightedSeriesAverage(payload, 'average_potential_efficiency', records),
            average_improvement_gap: weightedSeriesAverage(payload, 'average_improvement_gap', records),
        };
    }

    function formatInsightMetricValue(value, format, suffix) {
        if (value === null || value === undefined || value === '') {
            return 'N/A';
        }

        const rendered = formatNumber(value);

        return `${rendered}${suffix || ''}`;
    }

    function bucketsFromSeries(payload) {
        const labels = seriesLabels(payload);
        const series = Array.isArray(payload.series) ? payload.series : [];

        return series.map((item) => ({
            name: item.name || 'Series',
            unit: text(item.unit),
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

    function formatBucketValue(value, unit) {
        const formatted = formatNumber(value);

        if (unit === 'percent') {
            return `${formatted}%`;
        }

        if (unit === 'points') {
            return `${formatted} pts`;
        }

        return formatted;
    }

    function renderBars(buckets, prefix, unit) {
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
                    <div class="${prefix}-bar__label-row"><span>${escapeHtml(label)}</span><span>${formatBucketValue(parsed, unit)}</span></div>
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
            ${renderBars(item.buckets, prefix, item.unit)}
        </div>`).join('');
    }

    function renderEpcTrend(series, prefix) {
        const rendered = renderSeries(series, prefix);

        if (!rendered) {
            return '';
        }

        return `${rendered}<p class="${prefix}-chart__description">EPC trend views use certificate assessment records by period, not unique property counts.</p>`;
    }

    function renderEmptyState(message, prefix) {
        const textValue = text(message || 'No chart data is available for this view yet.');

        return `<p class="${prefix}-chart__empty">${escapeHtml(textValue)}</p>`;
    }

    function prefixFor(chart) {
        return chart.className.includes('cpi-postcode-area') ? 'cpi-postcode-area' : 'cpi-location';
    }

    function chartPalette() {
        return [
            themeValue('--cpi-chart-series-one-color', '#245a70'),
            themeValue('--cpi-chart-series-two-color', '#1f3f6d'),
            themeValue('--cpi-chart-series-three-color', '#9ec7bd'),
            themeValue('--cpi-chart-series-four-color', '#c7973e'),
            themeValue('--cpi-chart-series-five-color', '#d6674f'),
        ];
    }

    function shortChartLabel(value, limit = 30) {
        const label = text(value);

        if (label.length <= limit) {
            return label;
        }

        return `${label.slice(0, Math.max(0, limit - 1)).trim()}…`;
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
            const next = filterSourceComparison(payload, controls);

            return chart.dataset.cpiEpcInsights ? filterEpcTimelinePayload(next, controls) : next;
        }

        if (type === 'rating-comparison') {
            return filterRatingComparison(timeSlicedSeriesPayload(payload, controls), controls);
        }

        if (type === 'epc-time-series') {
            return filterEpcTimelinePayload(filterEpcTimeSeries(payload, chart), controls);
        }

        if (type === 'distribution') {
            return filterDistribution(payload, controls);
        }

        if (type === 'epc-opportunity-bars') {
            return timeSlicedOpportunityPayload(payload, controls);
        }

        if (type === 'epc-fuel-property-mix') {
            return timeSlicedFuelPropertyMixPayload(payload, controls);
        }

        return payload;
    }

    function updateSummary(module, controls) {
        if (module.querySelector('[data-cpi-current-view-value]')) {
            updateCurrentViewContext(module);
            return;
        }

        const summary = module.querySelector('[class$="-data-studio__summary"]');

        if (!summary) {
            updateCurrentViewContext(module);
            return;
        }

        const selected = selectedControlLabels(module);

        if (selected.length) {
            summary.textContent = `Current view: ${selected.join(' · ')}`;
        }

        updateCurrentViewContext(module);
    }

    function updateCurrentViewContext(module) {
        module.querySelectorAll('[data-cpi-current-view-value]').forEach((target) => {
            const key = target.dataset.cpiCurrentViewValue;
            const group = key ? module.querySelector(`[data-cpi-control-group="${key}"]`) : null;
            const select = group?.querySelector('[data-cpi-control-select]');
            const active = group?.querySelector('[data-cpi-control-option][aria-pressed="true"]');
            const item = target.closest('[data-cpi-current-view-item]');
            const itemLabel = item?.querySelector('span')?.textContent?.trim() || '';
            const label = select?.options?.[select.selectedIndex]?.textContent?.trim() || active?.textContent?.trim() || '';

            if (label) {
                target.textContent = label;

                if (item && itemLabel) {
                    item.setAttribute('aria-label', `${itemLabel}: ${label}`);
                }
            }
        });
    }

    function chartStatusText(chart, payload, controls) {
        const type = chart.dataset.cpiInteractiveChart;
        const parts = [];

        if ((type === 'source-comparison' || type === 'monthly-comparison') && controls.period) {
            parts.push(controls.period);
        }

        if (type === 'monthly-comparison' && controls.compare_with) {
            parts.push(controls.compare_with);
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

        if (type === 'rating-comparison' && controls.epc_insight_view) {
            parts.push('Current vs potential');
        }

        if (type === 'epc-time-series') {
            const selectedSeries = Array.isArray(payload.series) ? payload.series[0] : null;
            parts.push(text(selectedSeries?.name || 'EPC certificate records over time'));
        }

        if (
            (type === 'epc-time-series' || (type === 'source-comparison' && chart.dataset.cpiEpcInsights))
            && controls.epc_time_range
        ) {
            parts.push(controls.epc_time_range === 'all_periods' ? 'All records' : text(controls.epc_time_range).replace(/_/g, ' '));
        }

        if (
            ['rating-comparison', 'epc-opportunity-bars', 'epc-fuel-property-mix'].includes(type)
            && payload.time_slices
            && controls.epc_time_range
        ) {
            parts.push(controls.epc_time_range === 'all_periods' ? 'All records' : text(controls.epc_time_range).replace(/_/g, ' '));
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

    function activeEpcInsight(controls) {
        return controls.epc_insight_view || 'retrofit_opportunity';
    }

    function activeEpcInsightFromModule(module) {
        const group = module?.querySelector('[data-cpi-control-group="epc_insight_view"]');
        const select = group?.querySelector('[data-cpi-control-select]');
        const active = group?.querySelector('[data-cpi-control-option][aria-pressed="true"]');

        return select?.value || active?.dataset.cpiControlOption || active?.textContent.trim() || 'retrofit_opportunity';
    }

    function epcTimelineApplies(insight) {
        return [
            'rating_profile',
            'retrofit_opportunity',
            'evidence_volume',
            'fuel_heating',
            'property_type_trend',
            'property_type_opportunity',
            'fuel_by_property_type',
        ].includes(insight);
    }

    function epcSelectedTimelineRange(controls = {}) {
        return controls.epc_time_range || 'latest_3_years';
    }

    function epcRatingChartApplies(insight) {
        return insight === 'rating_profile';
    }

    function epcEvidenceViewApplies() {
        return false;
    }

    function epcControlApplies(key, insight, controls = {}) {
        if (key === 'epc_insight_view') {
            return true;
        }

        if (key === 'epc_view') {
            return epcEvidenceViewApplies();
        }

        if (key === 'epc_time_range') {
            return epcTimelineApplies(insight);
        }

        return true;
    }

    function controlGroupApplies(module, group) {
        const key = group?.dataset?.cpiControlGroup || '';

        if (!module || module.dataset.cpiModuleKey !== 'epc_status' || !key.startsWith('epc_')) {
            return !group?.hidden;
        }

        return epcControlApplies(key, activeEpcInsightFromModule(module), selectedControls(module, true));
    }

    function chartSupportsInsight(chart, insight) {
        const insights = text(chart.dataset.cpiEpcInsights).split(/\s+/).filter(Boolean);

        return !insights.length || insights.includes(insight);
    }

    function updateEpcInsightVisibility(module, controls) {
        const insight = activeEpcInsight(controls);
        const timelineActive = epcTimelineApplies(insight);
        const selectedRange = epcSelectedTimelineRange(controls);
        const timelineSummary = timelineActive ? epcTimelineSummary(module, controls) : {};

        module.querySelectorAll('[data-cpi-epc-insights]').forEach((chart) => {
            const requiresAllPeriods = chart.dataset.cpiEpcAllPeriodsOnly === 'true';
            const rangeMismatch = requiresAllPeriods && selectedRange !== 'all_periods';

            chart.hidden = !chartSupportsInsight(chart, insight) || rangeMismatch;
        });

        module.querySelectorAll('[data-cpi-interactive-chart="rating-comparison"]').forEach((chart) => {
            chart.hidden = !epcRatingChartApplies(insight);
        });

        module.querySelectorAll('[data-cpi-epc-all-record-summary]').forEach((summary) => {
            summary.hidden = true;
        });

        module.querySelectorAll('[data-cpi-epc-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.cpiEpcPanel !== insight;
        });

        module.querySelectorAll('[data-cpi-epc-conclusion]').forEach((panel) => {
            panel.hidden = panel.dataset.cpiEpcConclusion !== insight;

            panel.querySelectorAll('[data-cpi-epc-static-cards]').forEach((cards) => {
                cards.hidden = timelineActive;
            });

            panel.querySelectorAll('[data-cpi-epc-insight-metric]').forEach((metric) => {
                const key = metric.dataset.cpiEpcInsightMetric || '';
                const value = timelineSummary[key];
                const output = metric.querySelector('dd');

                if (!output) {
                    return;
                }

                if (!metric.dataset.cpiEpcDefaultValue) {
                    metric.dataset.cpiEpcDefaultValue = output.textContent || '';
                }

                output.textContent = timelineActive
                    ? formatInsightMetricValue(value, metric.dataset.cpiEpcInsightFormat || '', metric.dataset.cpiEpcInsightSuffix || '')
                    : metric.dataset.cpiEpcDefaultValue;
            });
        });

        module.querySelectorAll('[data-cpi-control-group]').forEach((group) => {
            const key = group.dataset.cpiControlGroup || '';
            const active = !key.startsWith('epc_') || epcControlApplies(key, insight, controls);

            group.hidden = !active;
            group.toggleAttribute('data-cpi-control-muted', !active);
            group.querySelectorAll('button, select').forEach((control) => {
                control.toggleAttribute('aria-disabled', !active);
                control.disabled = !active;
            });
        });

        module.querySelectorAll('[data-cpi-current-view-value]').forEach((target) => {
            const item = target.closest('[data-cpi-current-view-item]');
            const key = target.dataset.cpiCurrentViewValue || '';

            if (item) {
                item.hidden = key.startsWith('epc_') && !epcControlApplies(key, insight, controls);
            }
        });
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
        updateEpcInsightVisibility(module, selectedControls(module));

        const controls = selectedControls(module);
        updateSummary(module, controls);
        updateTradeSupportingEvidence(module, controls);
        updateEpcInsightVisibility(module, controls);

        module.querySelectorAll('[data-cpi-interactive-chart]').forEach((chart) => {
            if (chart.hidden) {
                return;
            }

            const output = chart.querySelector('[data-cpi-chart-output]');
            const chartType = chart.dataset.cpiInteractiveChart;
            const payload = filteredPayload(chart, controls);
            const prefix = prefixFor(chart);
            const series = chartType === 'distribution'
                ? bucketsFromItems(payload)
                : bucketsFromSeries(payload);
            const html = chartType === 'epc-fuel-property-mix'
                ? (output?.innerHTML || '')
                : (chartType === 'epc-time-series'
                ? renderEpcTrend(series, prefix)
                : renderSeries(series, prefix));

            updateChartStatus(chart, payload, controls);

            if (output) {
                output.innerHTML = html || renderEmptyState(payload.emptyText, prefix);
            }

            updateEchart(chart, payload);
        });
    }

    function validSeriesPayload(payload) {
        return seriesLabels(payload).length > 0
            && Array.isArray(payload.series)
            && payload.series.length > 0;
    }

    function validDistributionPayload(payload) {
        return Array.isArray(payload.items)
            && payload.items.some((item) => item && number(item.value) !== null && text(item.label || item.name || item.code) !== '');
    }

    function validFuelPropertyMixPayload(payload) {
        return seriesLabels(payload).length > 0
            && Array.isArray(payload.series)
            && payload.series.some((series) => Array.isArray(series?.data) && series.data.some((point) => {
                const value = isObject(point) ? point.value : point;

                return number(value) !== null && number(value) > 0;
            }));
    }

    function validOpportunityBarsPayload(payload) {
        return seriesLabels(payload).length > 0
            && Array.isArray(payload.series)
            && payload.series.some((series) => Array.isArray(series?.data) && series.data.some((value) => number(value) !== null));
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

    function numericPointValue(point) {
        const value = isObject(point) ? point.value : point;

        return number(value);
    }

    function pointCount(point) {
        return isObject(point) && number(point.count) !== null ? number(point.count) : null;
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
                axisLabel: {
                    color: ink,
                    fontWeight: 700,
                    formatter: (value) => shortChartLabel(value, 28),
                    width: 120,
                    overflow: 'truncate',
                },
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

    function fuelPropertyMixOption(payload) {
        if (!validFuelPropertyMixPayload(payload)) {
            return null;
        }

        const categories = seriesLabels(payload);
        const ink = themeValue('--cpi-color-ink', '#061126');
        const grid = themeValue('--cpi-chart-grid-color', 'rgba(100,116,139,0.18)');
        const recordCounts = Array.isArray(payload.record_counts) ? payload.record_counts : [];
        const fuelLabels = payload.series.map((series) => text(series.name || 'Fuel'));
        const chartHeight = Math.max(320, categories.length * Math.max(58, payload.series.length * 18) + 118);

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
                    fontSize: 12,
                    fontWeight: 600,
                },
                extraCssText: 'box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12); border-radius: 6px; max-width: 285px; white-space: normal;',
                formatter: (params) => {
                    const items = Array.isArray(params) ? params : [params];
                    const propertyType = text(items[0]?.axisValue || '');
                    const propertyIndex = categories.indexOf(propertyType);
                    const recordCount = number(recordCounts[propertyIndex]);
                    const rows = items
                        .filter((item) => number(item.value) !== null && number(item.value) > 0)
                        .sort((a, b) => number(b.value) - number(a.value))
                        .map((item) => {
                            const count = pointCount(item.data);
                            const countText = count === null ? '' : `<span style="color:#64748b;">${formatNumber(count)}</span>`;
                            const marker = item.marker ? `<span style="display:inline-flex;align-items:center;">${item.marker}</span>` : '';

                            return `<div style="display:grid;grid-template-columns:10px minmax(0,1fr) auto auto;align-items:center;gap:6px;">${marker}<span style="color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${text(item.seriesName)}</span><strong>${formatNumber(item.value)}%</strong>${countText}</div>`;
                        });

                    return [
                        '<div style="font-size:12px;line-height:1.45;min-width:230px;">',
                        `<div style="color:${ink};font-size:13px;font-weight:850;margin-bottom:2px;">${propertyType}</div>`,
                        recordCount === null ? '' : `<div style="color:#64748b;font-size:11px;margin-bottom:6px;">${formatNumber(recordCount)} EPC certificate records</div>`,
                        ...rows,
                        '</div>',
                    ].filter(Boolean).join('');
                },
            },
            legend: {
                type: fuelLabels.length > 4 ? 'scroll' : 'plain',
                top: 0,
                left: 'center',
                right: 'auto',
                itemGap: 10,
                itemHeight: 9,
                itemWidth: 16,
                textStyle: { color: ink, fontSize: 11, fontWeight: 700 },
                formatter: (name) => shortChartLabel(name, 28),
            },
            grid: {
                left: 10,
                right: 54,
                top: 74,
                bottom: 10,
                containLabel: true,
            },
            xAxis: {
                type: 'value',
                max: 100,
                axisLine: { show: false },
                axisTick: { show: false },
                splitLine: { lineStyle: { color: grid } },
                axisLabel: { formatter: (value) => `${value}%` },
            },
            yAxis: {
                type: 'category',
                data: categories,
                inverse: true,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: {
                    color: ink,
                    fontWeight: 800,
                    formatter: (value) => shortChartLabel(value, 18),
                },
            },
            series: payload.series.map((series) => ({
                name: text(series.name || 'Fuel'),
                type: 'bar',
                data: Array.isArray(series.data) ? series.data.map((point) => ({
                    value: numericPointValue(point) || 0,
                    count: pointCount(point),
                })) : [],
                barMaxWidth: 14,
                itemStyle: {
                    borderRadius: [0, 6, 6, 0],
                },
                label: {
                    show: true,
                    position: 'right',
                    color: ink,
                    fontSize: 11,
                    fontWeight: 800,
                    formatter: ({ value }) => Number(value) >= 8 ? `${formatNumber(value)}%` : '',
                },
            })),
            __cpiHeight: chartHeight,
        };
    }

    function opportunityBarsOption(payload) {
        if (!validOpportunityBarsPayload(payload)) {
            return null;
        }

        const categories = seriesLabels(payload);
        const ink = themeValue('--cpi-color-ink', '#061126');
        const grid = themeValue('--cpi-chart-grid-color', 'rgba(100,116,139,0.18)');
        const recordCounts = Array.isArray(payload.record_counts) ? payload.record_counts : [];
        const metricColor = (name) => {
            const normalised = text(name).toLowerCase();

            if (normalised.includes('poor')) {
                return themeValue('--cpi-chart-series-five-color', '#d6674f');
            }

            if (normalised.includes('retrofit')) {
                return themeValue('--cpi-chart-series-three-color', '#9ec7bd');
            }

            return themeValue('--cpi-chart-series-four-color', '#c7973e');
        };
        const supportedSeries = (payload.series || []).filter((series) => Array.isArray(series?.data)).slice(0, 3);
        const maxGap = Math.max(25, ...supportedSeries
            .filter((series) => text(series.unit) === 'score_points')
            .flatMap((series) => series.data.map((value) => number(value) || 0)));
        const panelHeight = Math.max(118, categories.length * 30 + 26);
        const panelGap = 48;
        const panelTop = 42;
        const chartHeight = panelTop + (supportedSeries.length * panelHeight) + ((supportedSeries.length - 1) * panelGap) + 28;

        return {
            ...baseEchartOption(),
            title: supportedSeries.map((series, index) => ({
                text: text(series.name || 'Metric'),
                left: 0,
                top: panelTop + (index * (panelHeight + panelGap)) - 30,
                textStyle: {
                    color: ink,
                    fontSize: 13,
                    fontWeight: 850,
                },
            })),
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
                extraCssText: 'box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12); border-radius: 6px; max-width: 230px; white-space: normal;',
                formatter: (params) => {
                    const items = Array.isArray(params) ? params : [params];
                    const category = text(items[0]?.axisValue || '');
                    const categoryIndex = categories.indexOf(category);
                    const count = number(recordCounts[categoryIndex]);

                    return [
                        '<div style="font-size:12px;line-height:1.45;min-width:190px;">',
                        `<div style="color:${ink};font-size:13px;font-weight:850;margin-bottom:2px;">${category}</div>`,
                        count === null ? '' : `<div style="color:#64748b;font-size:11px;margin-bottom:6px;">${formatNumber(count)} EPC certificate records</div>`,
                        ...supportedSeries.map((series) => {
                            const value = number(series.data[categoryIndex]);
                            const unit = text(series.unit || '');

                            if (value === null) {
                                return '';
                            }

                            return `<div style="display:flex;gap:12px;justify-content:space-between;"><span style="color:#64748b;">${text(series.name)}</span><strong>${formatNumber(value)}${unit === 'percent' ? '%' : ' pts'}</strong></div>`;
                        }),
                        '</div>',
                    ].filter(Boolean).join('');
                },
            },
            grid: supportedSeries.map((series, index) => ({
                left: 190,
                right: 104,
                top: panelTop + (index * (panelHeight + panelGap)),
                height: panelHeight,
                containLabel: true,
            })),
            xAxis: supportedSeries.map((series, index) => {
                const unit = text(series.unit || '');

                return {
                    type: 'value',
                    gridIndex: index,
                    max: unit === 'percent' ? 100 : Math.ceil(maxGap / 5) * 5,
                    axisLine: { show: false },
                    axisTick: { show: false },
                    splitLine: { lineStyle: { color: grid } },
                    axisLabel: {
                        formatter: (value) => unit === 'percent' ? `${value}%` : `${value} pts`,
                        fontSize: 10,
                    },
                };
            }),
            yAxis: supportedSeries.map((series, index) => ({
                type: 'category',
                gridIndex: index,
                data: categories,
                inverse: true,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: {
                    color: ink,
                    fontWeight: 850,
                    width: 178,
                    overflow: 'break',
                    lineHeight: 14,
                    formatter: (value) => shortChartLabel(value, 34),
                },
            })),
            series: supportedSeries.map((series, index) => {
                const unit = text(series.unit || '');

                return {
                    name: text(series.name || 'Metric'),
                    type: 'bar',
                    xAxisIndex: index,
                    yAxisIndex: index,
                    data: series.data.map((value) => number(value)),
                    barMaxWidth: 14,
                    itemStyle: {
                        color: metricColor(series.name),
                        borderRadius: [0, 7, 7, 0],
                    },
                    label: {
                        show: true,
                        position: 'right',
                        color: ink,
                        fontSize: 11,
                        fontWeight: 850,
                        formatter: ({ value }) => {
                            if (number(value) === null) {
                                return '';
                            }

                            return `${formatNumber(value)}${unit === 'percent' ? '%' : ' pts'}`;
                        },
                    },
                };
            }),
            __cpiHeight: chartHeight,
        };
    }

    function seriesOption(payload, chartType, chart = null) {
        if (!validSeriesPayload(payload)) {
            return null;
        }

        const categories = seriesLabels(payload);
        const isRating = chartType === 'rating-comparison';
        const isSource = chartType === 'source-comparison';
        const hasDenseLegend = isSource && payload.series.length > 4;
        const isEpcTrend = chartType === 'epc-time-series';
        const usesMedianAxis = chartType === 'monthly-comparison'
            && payload.series.some((series) => text(series.name).toLowerCase().includes('price'));
        const usesPercentAxis = chartType === 'epc-time-series'
            && payload.series.some((series) => text(series.unit) === 'percent');
        const usesPointAxis = chartType === 'epc-time-series'
            && payload.series.some((series) => text(series.unit) === 'points');
        const ink = themeValue('--cpi-color-ink', '#061126');
        const grid = themeValue('--cpi-chart-grid-color', 'rgba(100,116,139,0.18)');
        const axis = themeValue('--cpi-chart-grid-color', '#d7dee2');
        const monthlyColors = {
            selectedSales: themeValue('--cpi-chart-market-sales-color', '#3f6675'),
            comparisonSales: themeValue('--cpi-chart-market-comparison-sales-color', '#b9c6cc'),
            currentPrice: themeValue('--cpi-chart-market-price-color', '#cc735d'),
            comparisonPrice: themeValue('--cpi-chart-market-comparison-price-color', '#ecd1c8'),
        };
        const monthlyColor = (name) => {
            const lower = name.toLowerCase();

            if (lower.includes('price') && (lower.includes('previous') || lower.includes('same period') || lower.includes('comparison'))) {
                return monthlyColors.comparisonPrice;
            }

            if (lower.includes('price')) {
                return monthlyColors.currentPrice;
            }

            if (lower.includes('previous') || lower.includes('same period') || lower.includes('comparison')) {
                return monthlyColors.comparisonSales;
            }

            return monthlyColors.selectedSales;
        };

        return {
            ...baseEchartOption(),
            color: chartType === 'monthly-comparison'
                ? payload.series.map((series) => monthlyColor(text(series.name || 'Series')))
                : chartPalette(),
            legend: {
                type: hasDenseLegend ? 'scroll' : 'plain',
                orient: hasDenseLegend ? 'vertical' : 'horizontal',
                top: hasDenseLegend ? 18 : (isSource ? 10 : 0),
                left: hasDenseLegend ? 'auto' : 'center',
                right: hasDenseLegend ? 0 : (isSource ? 16 : 'auto'),
                width: hasDenseLegend ? 190 : (isSource ? '82%' : undefined),
                height: hasDenseLegend ? 120 : undefined,
                itemGap: hasDenseLegend ? 9 : (isSource ? 18 : (chartType === 'monthly-comparison' ? 12 : 14)),
                itemHeight: chartType === 'monthly-comparison' ? 9 : 10,
                itemWidth: chartType === 'monthly-comparison' ? 18 : 20,
                textStyle: { color: ink, fontSize: isSource ? 10 : (chartType === 'monthly-comparison' ? 11 : 12), fontWeight: 700 },
                formatter: (name) => shortChartLabel(name, hasDenseLegend ? 24 : (isSource ? 22 : 34)),
            },
            grid: chartType === 'monthly-comparison'
                ? {
                    left: 14,
                    right: 52,
                    top: 58,
                    bottom: 8,
                    containLabel: true,
                }
                : (isEpcTrend
                    ? {
                        left: 46,
                        right: 38,
                        top: 62,
                        bottom: 34,
                        containLabel: true,
                    }
                    : {
                        ...baseEchartOption().grid,
                        left: isSource ? 44 : baseEchartOption().grid.left,
                        right: hasDenseLegend ? 230 : (isSource ? 38 : baseEchartOption().grid.right),
                        bottom: isSource ? 42 : baseEchartOption().grid.bottom,
                        top: isSource ? (hasDenseLegend ? 34 : 84) : (payload.series.length > 4 ? 72 : baseEchartOption().grid.top),
                    }),
            xAxis: {
                type: 'category',
                data: categories,
                boundaryGap: isRating,
                axisLine: { lineStyle: { color: axis } },
                axisTick: { show: false },
                axisLabel: {
                    color: ink,
                    interval: 0,
                    hideOverlap: true,
                    margin: 12,
                    rotate: isEpcTrend ? 0 : (categories.length > 6 ? 22 : 0),
                },
            },
            yAxis: usesMedianAxis ? [
                {
                    type: 'value',
                    name: chartType === 'monthly-comparison' ? 'Sales' : 'Count',
                    axisLine: { show: false },
                    axisTick: { show: false },
                    splitLine: { lineStyle: { color: grid } },
                },
                {
                    type: 'value',
                    name: chartType === 'monthly-comparison' ? 'Median price' : 'Price',
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
                axisLabel: usesPercentAxis
                    ? { formatter: (value) => `${value}%` }
                    : (usesPointAxis ? { formatter: (value) => `${value} pts` } : undefined),
            },
            series: payload.series.map((series, index) => {
                const name = text(series.name || 'Series');
                const isPrice = name.toLowerCase().includes('price');
                const isComparison = name.toLowerCase().includes('previous') || name.toLowerCase().includes('same period') || name.toLowerCase().includes('comparison');
                const type = isRating ? 'bar' : (chartType === 'monthly-comparison' ? 'line' : (series.type === 'bar' ? 'bar' : 'line'));
                const color = chartType === 'monthly-comparison' ? monthlyColor(name) : undefined;

                return {
                    name,
                    type: isSource ? 'line' : type,
                    data: numericSeriesData(series),
                    yAxisIndex: usesMedianAxis && isPrice ? 1 : 0,
                    smooth: type === 'line',
                    symbolSize: type === 'line' ? 8 : undefined,
                    barMaxWidth: type === 'bar' ? 28 : undefined,
                    itemStyle: type === 'bar' ? { borderRadius: [8, 8, 0, 0] } : (color ? { color } : undefined),
                    lineStyle: type === 'line' ? { width: 3, color, type: isComparison ? 'dashed' : 'solid' } : undefined,
                    areaStyle: chartType === 'monthly-comparison' && type === 'line' && !isPrice && index === 0 ? { color, opacity: 0.12 } : undefined,
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

        if (type === 'epc-fuel-property-mix') {
            return fuelPropertyMixOption(payload);
        }

        if (type === 'epc-opportunity-bars') {
            return opportunityBarsOption(payload);
        }

        if (type === 'monthly-comparison' || type === 'source-comparison' || type === 'rating-comparison' || type === 'epc-time-series') {
            return seriesOption(payload, type, chart);
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
            if (option.__cpiHeight) {
                target.style.blockSize = `${option.__cpiHeight}px`;
            }
            delete option.__cpiHeight;
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
            updateUrlForModule(module);
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
            updateUrlForModule(module);
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
        updateUrlForModule(module);
    }

    function normaliseNearMeSearchInput(value) {
        const compact = String(value || '').trim().toUpperCase().replace(/[^A-Z0-9]/g, '');

        if (compact === '') {
            return null;
        }

        const match = compact.match(/^([A-Z]{1,2}[0-9][0-9A-Z]?)([0-9])?(?:[A-Z]{2})?$/);

        if (!match) {
            return null;
        }

        const district = match[1].toLowerCase();
        const sector = match[2] ? `-${match[2]}` : '';

        return `${district}${sector}`;
    }

    function showNearMeSearchMessage(form, message) {
        const container = form.closest('.cpi-near-me-search') || form;
        const output = container.querySelector('[data-cpi-near-me-search-message]');
        const input = form.querySelector('[data-cpi-near-me-search-input]');

        if (output) {
            output.textContent = message;
        }

        if (input) {
            input.setAttribute('aria-invalid', message === '' ? 'false' : 'true');
        }
    }

    function submitNearMeSearch(form) {
        const input = form.querySelector('[data-cpi-near-me-search-input]');
        const base = form.dataset.cpiNearMeBase || '/near-me/';
        const areaKey = normaliseNearMeSearchInput(input ? input.value : '');

        if (!areaKey) {
            showNearMeSearchMessage(form, 'Use a broad postcode area such as TR15 or TR15 2.');

            if (input) {
                input.focus();
            }

            return;
        }

        showNearMeSearchMessage(form, '');
        const separator = base.endsWith('/') ? '' : '/';
        window.location.assign(`${base}${separator}${areaKey}/`);
    }

    function init(root) {
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

        root.addEventListener('submit', (event) => {
            const form = event.target.closest('[data-cpi-near-me-search]');

            if (!form) {
                return;
            }

            event.preventDefault();
            submitNearMeSearch(form);
        });

        root.querySelectorAll('[data-cpi-control-select]').forEach(syncSelectWrapper);
        window.addEventListener('resize', () => resizeCharts(root));

        root.querySelectorAll('[data-cpi-module-root]').forEach((module) => {
            try {
                applyUrlControls(module);
                updateModule(module);
                updateUrlForModule(module);
            } catch (error) {
                module.dataset.cpiEnhancementError = 'true';
                module.dataset.cpiEnhancementErrorMessage = error?.message || 'Enhancement failed';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => init(document));
    } else {
        init(document);
    }
}());
