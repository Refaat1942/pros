<td class="bulk-select-col">
    <input type="checkbox"
           class="bulk-row-select"
           value="{{ $id }}"
           @if(!empty($disabled)) disabled title="{{ $disabledTitle ?? 'لا يمكن تحديد هذا السجل' }}" @endif
           aria-label="تحديد">
</td>
