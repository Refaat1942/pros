<?php

namespace Tests\Feature\Integrity;

use App\Http\Requests\Quote\ProcessApprovalLetterRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * H-2: يتحقق أن مسار خطاب الموافقة القادم من العميل مُقيَّد بـ approval_letters/
 * ولا يسمح بمسارات مطلقة أو تجاوز مسار — أول خط دفاع (قبل تحقق الخدمة من الوجود).
 */
class OcrLetterPathSecurityTest extends TestCase
{
    private function rules(): array
    {
        return (new ProcessApprovalLetterRequest())->rules();
    }

    private function passes(?string $letterPath): bool
    {
        $data = [
            'quote_no' => 'QT-1',
            'patient_name' => 'اسم',
            'approved_amount' => 100,
            'company_name' => 'جهة',
        ];

        if ($letterPath !== null) {
            $data['letter_path'] = $letterPath;
        }

        return Validator::make($data, $this->rules())->passes();
    }

    public function test_valid_approval_letter_path_is_accepted(): void
    {
        $this->assertTrue($this->passes('approval_letters/3f2504e0-4f89-11d3-9a0c-0305e82c3301.pdf'));
    }

    public function test_null_letter_path_is_allowed(): void
    {
        $this->assertTrue($this->passes(null));
    }

    public function test_path_traversal_is_rejected(): void
    {
        $this->assertFalse($this->passes('approval_letters/../../.env'));
        $this->assertFalse($this->passes('../../../etc/passwd'));
    }

    public function test_absolute_path_is_rejected(): void
    {
        $this->assertFalse($this->passes('/etc/passwd'));
        $this->assertFalse($this->passes('storage/app/public/branding/logo.png'));
    }

    public function test_other_directory_is_rejected(): void
    {
        $this->assertFalse($this->passes('public/secret.pdf'));
        $this->assertFalse($this->passes('approval_letters/sub/dir/file.pdf'));
    }
}
