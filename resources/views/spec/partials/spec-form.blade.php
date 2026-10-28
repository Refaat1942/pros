@php
    $specForm = config('spec_form');
@endphp
{{-- استمارة مواصفات الأطراف — نفس الإكسل. تُحفظ في #writtenItems كسطور «الاسم: القيمة». --}}
<div id="specFormSheet" class="spec-form-sheet">
    <h5 class="spec-form-title">📝 {{ $specForm['title'] }}</h5>

    @foreach ($specForm['rows'] as $row)
        <div class="spec-form-row">
            @foreach ($row as $section)
                <section class="spec-form-section">
                    <h6 class="spec-form-section__title">{{ $section['title'] }}</h6>
                    <table class="spec-form-table" data-no-paginate>
                        <tbody>
                            @foreach ($section['fields'] as $field)
                                <tr>
                                    <th scope="row">{{ $field }}</th>
                                    <td>
                                        <input type="text" class="spec-form-input" maxlength="200"
                                               data-spec-field="{{ $field }}" aria-label="{{ $field }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endforeach
        </div>
    @endforeach

    <section class="spec-form-section spec-form-section--wide">
        <h6 class="spec-form-section__title">{{ $specForm['other_title'] }}</h6>
        <textarea id="specFormOther" rows="3" class="spec-form-other" maxlength="2000"
                  placeholder="أي مواصفات تانية — كل بند في سطر"></textarea>
    </section>

    {{-- التخزين الفعلي (نفس الحقل القديم) — يتملى تلقائياً من الاستمارة قبل الحفظ. --}}
    <textarea id="writtenItems" hidden data-v-rules="max:5000" maxlength="5000"></textarea>
</div>

<style>
    .spec-form-sheet { border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; background: #fff; }
    .spec-form-title { font-weight: 800; color: #1e293b; text-align: center; margin: 0 0 12px; font-size: 15px; }
    .spec-form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px; margin-bottom: 12px; }
    .spec-form-section { border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden; }
    .spec-form-section--wide { margin-top: 4px; }
    .spec-form-section__title { margin: 0; padding: 8px 12px; background: #e2e8f0; font-weight: 800; font-size: 14px; color: #0f172a; text-align: center; }
    .spec-form-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .spec-form-table th { width: 42%; text-align: right; font-weight: 700; color: #334155; background: #f8fafc; padding: 6px 10px; border-top: 1px solid #e2e8f0; border-left: 1px solid #e2e8f0; }
    .spec-form-table td { padding: 4px 6px; border-top: 1px solid #e2e8f0; }
    .spec-form-input { width: 100%; border: 1px solid transparent; border-radius: 6px; padding: 5px 8px; font-size: 13px; background: transparent; }
    .spec-form-input:focus { outline: none; border-color: #94a3b8; background: #fff; }
    .spec-form-input:disabled, .spec-form-other:disabled { color: #475569; }
    .spec-form-other { width: 100%; border: 0; padding: 10px 12px; font-size: 13px; resize: vertical; }
</style>
