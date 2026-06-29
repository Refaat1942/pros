/**
 * شارات جهة المريض / الفوترة — نقدي، متعاقد، غير متعاقد، عسكري.
 */
(function (global) {
  'use strict';

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function resolveEntity(input) {
    if (!input) {
      return { label: '—', kind: '', badge: '', badge_class: '' };
    }

    if (input.entity && typeof input.entity === 'object') {
      return input.entity;
    }

    if (input.kind && input.label) {
      return input;
    }

    var label = input.entity_label
      || input.displayEntity
      || input.display_entity
      || input.company_name
      || input.company
      || '—';

    if (input.entity_badge || input.entity_badge_class) {
      return {
        label: label,
        kind: input.entity_kind || '',
        badge: input.entity_badge || '',
        badge_class: input.entity_badge_class || 'entity-badge',
      };
    }

    if (input.patient_type === 'military' || input.pathway === 'military' || input.path === 'military') {
      return {
        label: label,
        kind: 'military',
        badge: '🪖 عسكري',
        badge_class: 'entity-badge entity-badge--military',
      };
    }

    if (input.entity_kind === 'cash' || input.is_cash_civilian) {
      return {
        label: 'نقدي',
        kind: 'cash',
        badge: '💵 نقدي',
        badge_class: 'entity-badge entity-badge--cash',
      };
    }

    return { label: label, kind: '', badge: '', badge_class: '' };
  }

  function renderHtml(input) {
    var entity = resolveEntity(input);
    var badge = entity.badge
      ? '<span class="' + esc(entity.badge_class || 'entity-badge') + '">' + esc(entity.badge) + '</span>'
      : '';

    return '<div class="entity-cell">'
      + '<span class="entity-cell__label">' + esc(entity.label || '—') + '</span>'
      + badge
      + '</div>';
  }

  global.EntityBadges = {
    resolve: resolveEntity,
    renderHtml: renderHtml,
  };
})(typeof window !== 'undefined' ? window : this);
