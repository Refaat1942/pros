<style>
    #catalogFormModal.catalog-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1250;
        padding: 16px;
    }
    #catalogFormModal.catalog-modal-overlay.open {
        display: flex;
    }
    #catalogFormModal .catalog-form-modal {
        width: min(960px, 96vw);
        max-height: 92vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    #catalogFormModal .catalog-modal-body {
        overflow-y: auto;
        padding: 18px 22px;
    }
    .catalog-form-cards {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .catalog-form-card {
        background: #fff;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 12px;
        padding: 16px 18px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    }
    .catalog-form-card--attrs {
        background: #f8fafc;
    }
    .catalog-form-card__title {
        margin: 0 0 14px;
        font-size: 14px;
        font-weight: 800;
        color: var(--secondary, #334155);
    }
    .catalog-form-card__title--inline {
        margin: 0;
    }
    .catalog-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }
    .catalog-form-grid--basic {
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    }
    .catalog-form-grid__full {
        grid-column: 1 / -1;
    }
    .catalog-quick-dispense-label {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        cursor: pointer;
        margin: 4px 0 0;
        padding: 12px 14px;
        background: #fffbeb;
        border: 1px solid #fcd34d;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        line-height: 1.6;
        color: #92400e;
    }
    .catalog-quick-dispense-label input {
        width: auto;
        margin-top: 4px;
        flex-shrink: 0;
    }
    @media (min-width: 900px) {
        .catalog-form-grid--basic {
            grid-template-columns: repeat(5, 1fr);
        }
    }
    .catalog-attr-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
    }
    .catalog-attr-card {
        background: #fff;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 10px;
        padding: 14px;
    }
    .catalog-attr-card__label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--secondary, #334155);
    }
    .catalog-attr-card__req {
        color: #dc2626;
    }
    .catalog-form-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 6px;
    }
    .catalog-form-input {
        width: 100%;
        padding: 9px;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        font-family: inherit;
    }
    .catalog-form-hint {
        font-size: 12px;
        color: var(--text-muted, #64748b);
        margin: 6px 0 0;
    }
    .catalog-supplier-picker {
        margin-top: 0;
    }
    .catalog-combobox {
        position: relative;
    }
    .catalog-combobox__toggle {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 9px 12px;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        background: #fff;
        font-family: inherit;
        font-size: 14px;
        text-align: right;
        cursor: pointer;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .catalog-combobox__toggle:hover {
        border-color: #cbd5e1;
    }
    .catalog-combobox.is-open .catalog-combobox__toggle,
    .catalog-combobox__toggle:focus {
        outline: none;
        border-color: var(--primary, #2563eb);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }
    .catalog-combobox__value {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: var(--secondary, #334155);
    }
    .catalog-combobox__value.is-placeholder {
        color: var(--text-muted, #64748b);
    }
    .catalog-combobox__arrow {
        color: var(--text-muted, #64748b);
        font-size: 12px;
        flex-shrink: 0;
    }
    .catalog-combobox__dropdown {
        position: absolute;
        z-index: 50;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        overflow: hidden;
    }
    .catalog-combobox__search-wrap {
        padding: 8px;
        border-bottom: 1px solid var(--border, #e2e8f0);
        background: #f8fafc;
    }
    .catalog-combobox__search {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 6px;
        font-family: inherit;
        font-size: 13px;
    }
    .catalog-combobox__search:focus {
        outline: none;
        border-color: var(--primary, #2563eb);
    }
    .catalog-combobox__list {
        list-style: none;
        margin: 0;
        padding: 4px 0;
        max-height: 220px;
        overflow-y: auto;
    }
    .catalog-combobox__option {
        display: block;
        width: 100%;
        padding: 9px 12px;
        border: none;
        background: transparent;
        font-family: inherit;
        font-size: 13px;
        text-align: right;
        cursor: pointer;
        color: var(--secondary, #334155);
    }
    .catalog-combobox__option:hover,
    .catalog-combobox__option.is-selected {
        background: #eff6ff;
        color: var(--primary, #2563eb);
    }
    .catalog-combobox__empty {
        padding: 12px;
        font-size: 13px;
        color: var(--text-muted, #64748b);
        text-align: center;
    }
    .catalog-extra-prices {
        margin-top: 0;
    }
    .catalog-extra-prices__head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
    }
    .catalog-extra-prices__list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .catalog-form-error {
        margin-top: 10px;
        padding: 8px;
        background: #fee2e2;
        border-radius: 8px;
        color: #dc2626;
        font-size: 12px;
    }
</style>
