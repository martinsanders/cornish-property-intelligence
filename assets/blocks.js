(function (blocks, blockEditor, components, element, i18n) {
    'use strict';

    if (!blocks || !blockEditor || !components || !element || !i18n) {
        return;
    }

    const el = element.createElement;
    const Fragment = element.Fragment;
    const InspectorControls = blockEditor.InspectorControls;
    const useBlockProps = blockEditor.useBlockProps;
    const PanelBody = components.PanelBody;
    const SelectControl = components.SelectControl;
    const TextControl = components.TextControl;
    const ToggleControl = components.ToggleControl;
    const __ = i18n.__;

    const sourceOptions = [
        { label: __('Route context', 'cornish-property-intelligence'), value: 'route' },
        { label: __('Location slug', 'cornish-property-intelligence'), value: 'location' },
        { label: __('Postcode area key', 'cornish-property-intelligence'), value: 'postcode' },
    ];
    const contentTypeOptions = [
        { label: __('Title + summary', 'cornish-property-intelligence'), value: 'title_summary' },
        { label: __('Title', 'cornish-property-intelligence'), value: 'title' },
        { label: __('Summary', 'cornish-property-intelligence'), value: 'summary' },
        { label: __('Evidence note', 'cornish-property-intelligence'), value: 'evidence_note' },
    ];
    const displayModeOptions = [
        { label: __('Full module stack', 'cornish-property-intelligence'), value: 'stack' },
        { label: __('Single module', 'cornish-property-intelligence'), value: 'single' },
    ];
    const moduleOptions = [
        { label: __('Market', 'cornish-property-intelligence'), value: 'market' },
        { label: __('Trade / work activity', 'cornish-property-intelligence'), value: 'trade_work_activity' },
        { label: __('EPC / retrofit', 'cornish-property-intelligence'), value: 'epc_status' },
        { label: __('Change mix', 'cornish-property-intelligence'), value: 'change_mix' },
        { label: __('Opportunity signals', 'cornish-property-intelligence'), value: 'opportunity_signals' },
        { label: __('Published articles', 'cornish-property-intelligence'), value: 'published_articles' },
    ];
    const layoutVariantOptions = [
        { label: __('Standard module card', 'cornish-property-intelligence'), value: 'standard' },
        { label: __('Compact module card', 'cornish-property-intelligence'), value: 'compact' },
        { label: __('Chart first', 'cornish-property-intelligence'), value: 'chart_first' },
        { label: __('Metrics first', 'cornish-property-intelligence'), value: 'metrics_first' },
    ];
    const evidenceViewOptions = [
        { label: __('Evidence boundary note', 'cornish-property-intelligence'), value: 'boundary_note' },
        { label: __('Fallback journey', 'cornish-property-intelligence'), value: 'fallback_journey' },
        { label: __('Module availability', 'cornish-property-intelligence'), value: 'module_availability' },
        { label: __('Full evidence context', 'cornish-property-intelligence'), value: 'full_context' },
    ];
    const evidenceLayoutOptions = [
        { label: __('Journey steps', 'cornish-property-intelligence'), value: 'journey' },
        { label: __('Compact cards', 'cornish-property-intelligence'), value: 'cards' },
        { label: __('Inline note', 'cornish-property-intelligence'), value: 'inline' },
    ];
    const headingOptions = [1, 2, 3, 4, 5, 6].map((level) => ({
        label: `H${level}`,
        value: level,
    }));
    const textAlignOptions = [
        { label: __('Inherit', 'cornish-property-intelligence'), value: '' },
        { label: __('Left', 'cornish-property-intelligence'), value: 'left' },
        { label: __('Centre', 'cornish-property-intelligence'), value: 'center' },
        { label: __('Right', 'cornish-property-intelligence'), value: 'right' },
    ];
    const sharedAttributes = {
        dataSource: { type: 'string', default: 'route' },
        locationSlug: { type: 'string', default: '' },
        areaKey: { type: 'string', default: '' },
        textAlign: { type: 'string', default: '' },
    };

    function setAttribute(setAttributes, key) {
        return function (value) {
            setAttributes({ [key]: value });
        };
    }

    function inspectorControls(props, options) {
        const attributes = props.attributes;
        const setAttributes = props.setAttributes;
        const fields = [];
        const designFields = [];

        if (options.source !== false) {
            const dataSource = attributes.dataSource || inferSource(attributes);

            fields.push(el(SelectControl, {
                key: 'dataSource',
                label: __('Source', 'cornish-property-intelligence'),
                help: __('Use route context in virtual templates, or choose a manual source on ordinary pages.', 'cornish-property-intelligence'),
                value: dataSource,
                options: sourceOptions,
                onChange: setAttribute(setAttributes, 'dataSource'),
            }));

            if (dataSource === 'location') {
                fields.push(el(TextControl, {
                    key: 'locationSlug',
                    label: __('Location slug', 'cornish-property-intelligence'),
                    value: attributes.locationSlug || '',
                    placeholder: 'truro',
                    onChange: setAttribute(setAttributes, 'locationSlug'),
                }));
            }

            if (dataSource === 'postcode') {
                fields.push(el(TextControl, {
                    key: 'areaKey',
                    label: __('Postcode area key', 'cornish-property-intelligence'),
                    value: attributes.areaKey || '',
                    placeholder: 'tr15-2',
                    onChange: setAttribute(setAttributes, 'areaKey'),
                }));
            }
        }

        if (options.kind === 'search') {
            fields.push(el(SelectControl, {
                key: 'searchMode',
                label: __('Search mode', 'cornish-property-intelligence'),
                value: attributes.searchMode || 'near_me',
                options: [
                    { label: __('Near Me postcode area', 'cornish-property-intelligence'), value: 'near_me' },
                    { label: __('Location search placeholder', 'cornish-property-intelligence'), value: 'location' },
                ],
                onChange: setAttribute(setAttributes, 'searchMode'),
            }));
            fields.push(el(TextControl, {
                key: 'labelText',
                label: __('Search label', 'cornish-property-intelligence'),
                value: attributes.labelText || '',
                placeholder: __('Search another postcode area', 'cornish-property-intelligence'),
                onChange: setAttribute(setAttributes, 'labelText'),
            }));
            fields.push(el(TextControl, {
                key: 'placeholderText',
                label: __('Placeholder text', 'cornish-property-intelligence'),
                value: attributes.placeholderText || '',
                placeholder: __('TR15 2', 'cornish-property-intelligence'),
                onChange: setAttribute(setAttributes, 'placeholderText'),
            }));
            fields.push(el(TextControl, {
                key: 'buttonText',
                label: __('Button label', 'cornish-property-intelligence'),
                value: attributes.buttonText || '',
                placeholder: __('Search', 'cornish-property-intelligence'),
                onChange: setAttribute(setAttributes, 'buttonText'),
            }));
            fields.push(toggleControl(attributes, setAttributes, 'showHint', __('Show helper text', 'cornish-property-intelligence')));
        }

        if (options.kind === 'content') {
            fields.push(el(SelectControl, {
                key: 'contentType',
                label: __('Content type', 'cornish-property-intelligence'),
                value: attributes.contentType || 'title_summary',
                options: contentTypeOptions,
                onChange: setAttribute(setAttributes, 'contentType'),
            }));
            if (['title', 'title_summary'].includes(attributes.contentType || 'title_summary')) {
                fields.push(el(SelectControl, {
                    key: 'headingLevel',
                    label: __('Heading level', 'cornish-property-intelligence'),
                    value: attributes.headingLevel || 2,
                    options: headingOptions,
                    onChange: function (value) {
                        setAttributes({ headingLevel: Number(value) });
                    },
                }));
            }
            fields.push(toggleControl(attributes, setAttributes, 'showKicker', __('Show kicker', 'cornish-property-intelligence')));
            if (['summary', 'title_summary'].includes(attributes.contentType || 'title_summary')) {
                fields.push(toggleControl(attributes, setAttributes, 'showSummary', __('Show summary', 'cornish-property-intelligence')));
            }
        }

        if (options.kind === 'module') {
            const mode = attributes.displayMode || 'stack';
            fields.push(el(SelectControl, {
                key: 'displayMode',
                label: __('Display mode', 'cornish-property-intelligence'),
                value: mode,
                options: displayModeOptions,
                onChange: setAttribute(setAttributes, 'displayMode'),
            }));
            if (mode === 'single') {
                fields.push(el(SelectControl, {
                    key: 'moduleType',
                    label: __('Module', 'cornish-property-intelligence'),
                    value: normaliseModuleType(attributes.moduleType || 'market'),
                    options: moduleOptions,
                    onChange: setAttribute(setAttributes, 'moduleType'),
                }));
            }
            fields.push(el(SelectControl, {
                key: 'layoutVariant',
                label: __('Preview layout', 'cornish-property-intelligence'),
                value: attributes.layoutVariant || 'standard',
                options: layoutVariantOptions,
                onChange: setAttribute(setAttributes, 'layoutVariant'),
            }));
            fields.push(toggleControl(attributes, setAttributes, 'showChart', __('Show chart', 'cornish-property-intelligence')));
            fields.push(toggleControl(attributes, setAttributes, 'showMetrics', __('Show metrics', 'cornish-property-intelligence')));
            fields.push(toggleControl(attributes, setAttributes, 'showControls', __('Show controls', 'cornish-property-intelligence')));
            fields.push(toggleControl(attributes, setAttributes, 'showSupportingEvidence', __('Show supporting evidence', 'cornish-property-intelligence')));
            fields.push(toggleControl(attributes, setAttributes, 'showEvidenceNote', __('Show evidence note', 'cornish-property-intelligence')));
            fields.push(toggleControl(attributes, setAttributes, 'showMissingModules', __('Show fallback/missing states', 'cornish-property-intelligence')));
        }

        if (options.kind === 'evidence') {
            fields.push(el(SelectControl, {
                key: 'evidenceView',
                label: __('Evidence view', 'cornish-property-intelligence'),
                value: attributes.evidenceView || 'boundary_note',
                options: evidenceViewOptions,
                onChange: setAttribute(setAttributes, 'evidenceView'),
            }));
            fields.push(el(SelectControl, {
                key: 'evidenceLayout',
                label: __('Layout variant', 'cornish-property-intelligence'),
                value: attributes.evidenceLayout || 'journey',
                options: evidenceLayoutOptions,
                onChange: setAttribute(setAttributes, 'evidenceLayout'),
            }));
            fields.push(toggleControl(attributes, setAttributes, 'showAssociatedGuide', __('Show associated guide', 'cornish-property-intelligence')));
            fields.push(toggleControl(attributes, setAttributes, 'showAssociatedArticles', __('Show associated articles', 'cornish-property-intelligence')));
            fields.push(toggleControl(attributes, setAttributes, 'showMissingEvidenceNotes', __('Show missing evidence notes', 'cornish-property-intelligence')));
            fields.push(toggleControl(attributes, setAttributes, 'showFallbackReason', __('Show fallback reason', 'cornish-property-intelligence')));
        }

        if (options.textAlign) {
            designFields.push(el(SelectControl, {
                key: 'textAlign',
                label: __('Text alignment', 'cornish-property-intelligence'),
                value: attributes.textAlign || '',
                options: textAlignOptions,
                onChange: setAttribute(setAttributes, 'textAlign'),
            }));
        }

        return el(InspectorControls, null,
            el(PanelBody, { title: __('Cornish Property settings', 'cornish-property-intelligence'), initialOpen: true }, fields),
            designFields.length > 0 ? el(PanelBody, { title: __('Typography', 'cornish-property-intelligence'), initialOpen: false }, designFields) : null
        );
    }

    function toggleControl(attributes, setAttributes, key, label) {
        return el(ToggleControl, {
            key,
            label,
            checked: attributes[key] !== false,
            onChange: setAttribute(setAttributes, key),
        });
    }

    function preview(props, options) {
        const attributes = props.attributes;
        const blockProps = useBlockProps({
            className: designClassName(attributes, `cpi-block-editor-preview cpi-block-editor-preview--${options.kind}`),
        });

        return el(Fragment, null,
            inspectorControls(props, options),
            el('div', blockProps, previewContent(options.kind, attributes))
        );
    }

    function previewContent(kind, attributes) {
        if (kind === 'search') {
            return searchPreview(attributes);
        }

        if (kind === 'content') {
            return contentPreview(attributes);
        }

        if (kind === 'module') {
            return moduleBlockPreview(attributes);
        }

        return evidencePreview(attributes);
    }

    function searchPreview(attributes) {
        if ((attributes.searchMode || 'near_me') !== 'near_me') {
            return el('section', { className: 'cpi-block-editor-preview__empty-state' },
                el('h3', null, __('Location search placeholder', 'cornish-property-intelligence')),
                el('p', null, __('This mode is reserved for a later public search release.', 'cornish-property-intelligence'))
            );
        }

        return el('section', { className: 'cpi-near-me-search cpi-near-me-search--block cpi-block-editor-preview__rendered-search' },
            el('div', { className: 'cpi-near-me-search__copy' },
                el('p', { className: 'cpi-near-me-search__label' }, attributes.labelText || __('Search another postcode area', 'cornish-property-intelligence')),
                attributes.showHint === false ? null : el('p', { className: 'cpi-near-me-search__hint' }, __('Use a broad postcode area such as TR15, TR15 2 or TR15-2.', 'cornish-property-intelligence'))
            ),
            el('div', { className: 'cpi-near-me-search__form' },
                el('span', { className: 'cpi-near-me-search__input cpi-block-editor-preview__input' }, attributes.placeholderText || __('TR15 2', 'cornish-property-intelligence')),
                el('span', { className: 'cpi-button cpi-button--primary cpi-near-me-search__button wp-element-button cpi-block-editor-preview__button' }, attributes.buttonText || __('Search', 'cornish-property-intelligence'))
            )
        );
    }

    function contentPreview(attributes) {
        const contentType = attributes.contentType || 'title_summary';
        const parts = [];

        if (attributes.showKicker) {
            parts.push(el('p', { key: 'kicker', className: 'cpi-virtual-page__eyebrow' }, sourceLabel(attributes)));
        }

        if (['title', 'title_summary'].includes(contentType)) {
            const tag = `h${attributes.headingLevel || 2}`;
            parts.push(el(tag, { key: 'title', className: 'cpi-intelligence-block-title' }, payloadTitle(attributes)));
        }

        if (['summary', 'title_summary'].includes(contentType) && attributes.showSummary !== false) {
            parts.push(el('section', { key: 'summary', className: 'cpi-summary cpi-block-editor-preview__summary' },
                el('p', { className: 'cpi-summary__lead' }, payloadSummary(attributes)),
                el('p', null, __('Approved public evidence appears here when the selected JSON payload provides it.', 'cornish-property-intelligence'))
            ));
        }

        if (contentType === 'evidence_note') {
            parts.push(evidenceBoundaryNote('content-note'));
        }

        return el('div', { className: `cpi-block-editor-preview__content cpi-block-editor-preview__content--${contentType}` }, parts);
    }

    function moduleBlockPreview(attributes) {
        const mode = attributes.displayMode || 'stack';
        const modules = mode === 'single'
            ? [normaliseModuleType(attributes.moduleType || 'market')]
            : ['executive_answer', 'market', 'trade_work_activity', 'epc_status', 'change_mix', 'opportunity_signals', 'published_articles'];

        return el('div', { className: `cpi-location-modules cpi-block-editor-preview__modules cpi-block-editor-preview__modules--${attributes.layoutVariant || 'standard'}` },
            modules.map((moduleType) => modulePreview(moduleType, attributes))
        );
    }

    function modulePreview(moduleType, attributes) {
        const copy = moduleCopy(moduleType);
        const showChart = attributes.showChart !== false && !['executive_answer', 'opportunity_signals', 'published_articles'].includes(moduleType);
        const showMetrics = attributes.showMetrics !== false && !['executive_answer', 'published_articles'].includes(moduleType);
        const showControls = attributes.showControls !== false && showChart;
        const showSupportingEvidence = attributes.showSupportingEvidence !== false && moduleType !== 'executive_answer';
        const pieces = [];

        pieces.push(el('header', { key: 'header', className: 'cpi-location-module__header' },
            moduleType === 'executive_answer' ? el('p', { className: 'cpi-location-module__eyebrow' }, __('Executive answer', 'cornish-property-intelligence')) : null,
            el('h3', { className: 'cpi-location-module__title' }, copy.title),
            el('p', { className: 'cpi-location-module__description' }, copy.description)
        ));

        if ((attributes.layoutVariant || 'standard') === 'metrics_first' && showMetrics) {
            pieces.push(metricPreview(moduleType));
        }

        if (showControls) {
            pieces.push(controlPreview(moduleType));
        }

        if (showChart) {
            pieces.push(chartPreview(moduleType));
        }

        if ((attributes.layoutVariant || 'standard') !== 'metrics_first' && showMetrics) {
            pieces.push(metricPreview(moduleType));
        }

        if (moduleType === 'executive_answer') {
            pieces.push(signalPreview());
        }

        if (showSupportingEvidence) {
            pieces.push(el('p', { key: 'support', className: 'cpi-block-editor-preview__quiet-note' }, __('Supporting evidence and privacy notes use the selected public JSON payload.', 'cornish-property-intelligence')));
        }

        return el('article', { key: moduleType, className: `cpi-location-module cpi-location-module--${moduleType}` }, pieces);
    }

    function controlPreview(moduleType) {
        const label = moduleType === 'epc_status'
            ? __('Current vs potential', 'cornish-property-intelligence')
            : __('Past 6 months · same period last year', 'cornish-property-intelligence');

        return el('div', { key: 'controls', className: 'cpi-block-editor-preview__control-strip' },
            el('span', null, __('Chart view', 'cornish-property-intelligence')),
            el('strong', null, label)
        );
    }

    function chartPreview(moduleType) {
        const labels = moduleType === 'epc_status'
            ? ['A-B', 'C', 'D', 'E-G']
            : ['Jan', 'Feb', 'Mar', 'Apr', 'May'];

        return el('div', { key: 'chart', className: 'cpi-block-editor-preview__chart' },
            labels.map((label, index) => el('span', {
                key: label,
                style: { blockSize: `${34 + (index * 13) % 48}%` },
            }, el('em', null, label)))
        );
    }

    function metricPreview(moduleType) {
        const labels = moduleType === 'epc_status'
            ? [['Average score', '57'], ['Potential', '74'], ['Improvement gap', '17']]
            : [['Current', 'Available'], ['Comparison', 'Context'], ['Coverage', 'Aggregate']];

        return el('div', { key: 'metrics', className: 'cpi-location-module__metrics cpi-block-editor-preview__metrics' },
            labels.map(([label, value]) => el('div', { key: label, className: 'cpi-location-module__metric' },
                el('span', null, label),
                el('strong', null, value)
            ))
        );
    }

    function signalPreview() {
        return el('ul', { key: 'signals', className: 'cpi-location-module__signals cpi-block-editor-preview__signals' },
            ['Market', 'Work activity', 'EPC / retrofit'].map((label) => el('li', { key: label },
                el('strong', null, label),
                el('span', null, __('Signal shown when present', 'cornish-property-intelligence'))
            ))
        );
    }

    function evidencePreview(attributes) {
        const view = attributes.evidenceView || 'boundary_note';

        if (view === 'fallback_journey') {
            return fallbackPreview(attributes);
        }

        if (view === 'module_availability') {
            return moduleAvailabilityPreview(attributes);
        }

        if (view === 'full_context') {
            return el('div', { className: 'cpi-block-editor-preview__evidence-stack' },
                evidenceBoundaryNote('full-note'),
                fallbackPreview(attributes),
                moduleAvailabilityPreview(attributes)
            );
        }

        return evidenceBoundaryNote('boundary');
    }

    function evidenceBoundaryNote(key) {
        return el('p', { key, className: 'cpi-evidence-note cpi-block-editor-preview__evidence-note' }, __('This view uses approved public-safe aggregate intelligence only.', 'cornish-property-intelligence'));
    }

    function fallbackPreview(attributes) {
        const steps = [
            __('Sector evidence', 'cornish-property-intelligence'),
            __('District context', 'cornish-property-intelligence'),
            __('Associated guide', 'cornish-property-intelligence'),
            __('Cornwall context', 'cornish-property-intelligence'),
        ];

        return el('section', { key: 'fallback', className: `cpi-postcode-area-fallback-context cpi-location-local-context cpi-block-editor-preview__fallback cpi-block-editor-preview__fallback--${attributes.evidenceLayout || 'journey'}` },
            el('article', { className: 'cpi-location-local-context__main cpi-postcode-area-fallback-context__main' },
                el('p', { className: 'cpi-virtual-page__eyebrow' }, __('Local context', 'cornish-property-intelligence')),
                el('h2', null, __('Postcode evidence context', 'cornish-property-intelligence')),
                attributes.showFallbackReason === false ? null : el('p', null, __('Evidence widens only where the public export provides fallback context.', 'cornish-property-intelligence')),
                el('div', { className: 'cpi-block-editor-preview__journey-mock' }, steps.map((label) => el('span', { key: label }, label)))
            )
        );
    }

    function moduleAvailabilityPreview(attributes) {
        const items = [
            ['Market', __('Available', 'cornish-property-intelligence')],
            ['EPC / retrofit', __('Available', 'cornish-property-intelligence')],
            ['Planning', attributes.showMissingEvidenceNotes === false ? '' : __('Not ready yet', 'cornish-property-intelligence')],
        ].filter(([, status]) => status !== '');

        return el('section', { key: 'availability', className: 'cpi-module-availability cpi-block-editor-preview__availability' },
            el('h3', null, __('Evidence availability', 'cornish-property-intelligence')),
            el('ul', { className: 'cpi-module-availability__list' },
                items.map(([label, status]) => el('li', { key: label, className: 'cpi-module-availability__item' },
                    el('strong', null, label),
                    el('span', null, status)
                ))
            )
        );
    }

    function payloadTitle(attributes) {
        const source = attributes.dataSource || inferSource(attributes);

        if (source === 'location') {
            return previewLocationName(attributes.locationSlug || '');
        }

        if (source === 'postcode') {
            return previewNearMeName(attributes.areaKey || '');
        }

        return __('Property intelligence from route context', 'cornish-property-intelligence');
    }

    function payloadSummary(attributes) {
        const source = attributes.dataSource || inferSource(attributes);

        if (source === 'location') {
            const value = (attributes.locationSlug || '').trim();
            const label = value === '' ? __('this location', 'cornish-property-intelligence') : titleCase(value.replace(/-/g, ' '));

            return `${label} market movement, work activity, EPC status and opportunity signals from approved public evidence.`;
        }

        if (source === 'postcode') {
            const value = (attributes.areaKey || '').trim();
            const label = value === '' ? __('this postcode area', 'cornish-property-intelligence') : value.toUpperCase().replace('-', ' ');

            return `Approved aggregate evidence for ${label}, with wider fallback context where the export provides it.`;
        }

        return __('The selected route supplies the public-safe title, summary and evidence context.', 'cornish-property-intelligence');
    }

    function previewLocationName(slug) {
        const value = (slug || '').trim();

        return value === ''
            ? __('Location title from route context', 'cornish-property-intelligence')
            : `${titleCase(value.replace(/-/g, ' '))} Property Intelligence`;
    }

    function previewNearMeName(areaKey) {
        const value = (areaKey || '').trim();

        return value === ''
            ? __('Near Me title from route context', 'cornish-property-intelligence')
            : `${value.toUpperCase().replace('-', ' ')} postcode area`;
    }

    function sourceLabel(attributes) {
        const source = attributes.dataSource || inferSource(attributes);

        return source === 'postcode'
            ? __('Near Me', 'cornish-property-intelligence')
            : __('Location Intelligence', 'cornish-property-intelligence');
    }

    function moduleCopy(moduleType) {
        return {
            executive_answer: {
                title: __('What the evidence suggests', 'cornish-property-intelligence'),
                description: __('A concise answer assembled from available public module signals.', 'cornish-property-intelligence'),
            },
            market: {
                title: __('How active is the property market?', 'cornish-property-intelligence'),
                description: __('Monthly sales, price comparisons, property type mix and supporting evidence.', 'cornish-property-intelligence'),
            },
            trade_work_activity: {
                title: __('Is real property work activity rising?', 'cornish-property-intelligence'),
                description: __('Planning, building control and competent person signals where public aggregate evidence exists.', 'cornish-property-intelligence'),
            },
            epc_status: {
                title: __('How much retrofit potential exists here?', 'cornish-property-intelligence'),
                description: __('Current and potential EPC rating distribution from approved aggregate evidence.', 'cornish-property-intelligence'),
            },
            change_mix: {
                title: __('What type of property change is happening?', 'cornish-property-intelligence'),
                description: __('A visual read of property change categories where available.', 'cornish-property-intelligence'),
            },
            opportunity_signals: {
                title: __('Opportunity signals', 'cornish-property-intelligence'),
                description: __('A concise read of strongest aggregate signals and likely audiences.', 'cornish-property-intelligence'),
            },
            published_articles: {
                title: __('Published articles', 'cornish-property-intelligence'),
                description: __('Further reading connected to the public evidence view.', 'cornish-property-intelligence'),
            },
        }[moduleType] || {
            title: titleCase(moduleType.replace(/_/g, ' ')),
            description: __('Public-safe aggregate module preview.', 'cornish-property-intelligence'),
        };
    }

    function normaliseModuleType(value) {
        return value === 'epc' ? 'epc_status' : value;
    }

    function inferSource(attributes) {
        if ((attributes.locationSlug || '').trim() !== '') {
            return 'location';
        }

        if ((attributes.areaKey || '').trim() !== '') {
            return 'postcode';
        }

        return 'route';
    }

    function titleCase(value) {
        return value.replace(/\b\w/g, (letter) => letter.toUpperCase());
    }

    function designClassName(attributes, baseClass) {
        const classes = [baseClass, 'cpi-dynamic-block'];
        const textAlign = attributes.textAlign || '';

        if (textAlign) {
            classes.push(`has-text-align-${textAlign}`);
        }

        return classes.join(' ');
    }

    function registerDynamicBlock(name, settings) {
        blocks.registerBlockType(`cornish-property/${name}`, {
            apiVersion: 3,
            title: settings.title,
            category: 'cornish-property',
            icon: settings.icon,
            description: settings.description,
            attributes: settings.attributes,
            example: settings.example || {},
            edit: function (props) {
                return preview(props, settings.preview);
            },
            save: function () {
                return null;
            },
            supports: {
                html: false,
                inserter: settings.inserter !== false,
            },
        });
    }

    registerDynamicBlock('search', {
        title: __('Cornish Property Search', 'cornish-property-intelligence'),
        icon: 'search',
        description: __('Near Me postcode area search form.', 'cornish-property-intelligence'),
        attributes: {
            searchMode: { type: 'string', default: 'near_me' },
            labelText: { type: 'string', default: '' },
            placeholderText: { type: 'string', default: '' },
            buttonText: { type: 'string', default: '' },
            showHint: { type: 'boolean', default: true },
            textAlign: { type: 'string', default: '' },
        },
        example: {
            attributes: {
                labelText: __('Search another postcode area', 'cornish-property-intelligence'),
                placeholderText: __('TR15 2', 'cornish-property-intelligence'),
                buttonText: __('Search', 'cornish-property-intelligence'),
                showHint: true,
            },
        },
        preview: { kind: 'search', source: false, textAlign: true },
    });

    registerDynamicBlock('content', {
        title: __('Cornish Property Content', 'cornish-property-intelligence'),
        icon: 'text-page',
        description: __('Title, summary or evidence note from public JSON.', 'cornish-property-intelligence'),
        attributes: {
            ...sharedAttributes,
            contentType: { type: 'string', default: 'title_summary' },
            headingLevel: { type: 'number', default: 2 },
            showKicker: { type: 'boolean', default: false },
            showSummary: { type: 'boolean', default: true },
        },
        example: {
            attributes: {
                contentType: 'title_summary',
                headingLevel: 1,
                showKicker: true,
                showSummary: true,
            },
        },
        preview: { kind: 'content', textAlign: true },
    });

    registerDynamicBlock('module', {
        title: __('Cornish Property Module', 'cornish-property-intelligence'),
        icon: 'analytics',
        description: __('Full module stack or selected module from public JSON.', 'cornish-property-intelligence'),
        attributes: {
            ...sharedAttributes,
            displayMode: { type: 'string', default: 'stack' },
            moduleType: { type: 'string', default: 'market' },
            layoutVariant: { type: 'string', default: 'standard' },
            showChart: { type: 'boolean', default: true },
            showMetrics: { type: 'boolean', default: true },
            showControls: { type: 'boolean', default: true },
            showSupportingEvidence: { type: 'boolean', default: true },
            showEvidenceNote: { type: 'boolean', default: true },
            showMissingModules: { type: 'boolean', default: true },
        },
        example: {
            attributes: {
                displayMode: 'single',
                moduleType: 'market',
                layoutVariant: 'chart_first',
                showChart: true,
                showMetrics: true,
                showControls: true,
                showSupportingEvidence: true,
            },
        },
        preview: { kind: 'module' },
    });

    registerDynamicBlock('evidence', {
        title: __('Cornish Property Evidence', 'cornish-property-intelligence'),
        icon: 'privacy',
        description: __('Fallback journey, availability and evidence boundary context.', 'cornish-property-intelligence'),
        attributes: {
            ...sharedAttributes,
            evidenceView: { type: 'string', default: 'boundary_note' },
            evidenceLayout: { type: 'string', default: 'journey' },
            showAssociatedGuide: { type: 'boolean', default: true },
            showAssociatedArticles: { type: 'boolean', default: true },
            showMissingEvidenceNotes: { type: 'boolean', default: true },
            showFallbackReason: { type: 'boolean', default: true },
        },
        example: {
            attributes: {
                evidenceView: 'full_context',
                evidenceLayout: 'journey',
                showAssociatedGuide: true,
                showAssociatedArticles: true,
                showMissingEvidenceNotes: true,
            },
        },
        preview: { kind: 'evidence' },
    });

}(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n));
