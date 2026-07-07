<?php

namespace App\Http\Requests;

use App\Rules\EgyptianMobile;
use App\Rules\EgyptianNationalId;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

class BaseRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['phone', 'national_id'] as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $raw = $this->input($key);

            if ($raw === null || $raw === '') {
                $this->merge([$key => null]);

                continue;
            }

            if (! is_scalar($raw)) {
                continue;
            }

            $trimmed = trim((string) $raw);

            if ($trimmed === '') {
                $this->merge([$key => null]);

                continue;
            }

            // لا تحوّل النص غير الرقمي إلى null — اتركه ليفشل التحقق صراحةً
            if (preg_match('/\D/u', $trimmed)) {
                continue;
            }

            $this->merge([$key => $trimmed]);
        }
    }

    protected function failedValidation(Validator $validator)
    {
        if (request()->is('api/*')) {
            throw new HttpResponseException($this->response('fail', $validator->errors()->first()));
        } else {
            throw (new ValidationException($validator))
                ->errorBag($this->errorBag)
                ->redirectTo($this->getRedirectUrl());
        }
    }

    protected function mimesVideo(): string
    {
        $extension = [
            'mp4',
            'avi',
            'mov',
            'wmv',
            'flv',
            'mkv',
            'webm',
            '3gp',
            'ogv',
            'mpeg',
            'm4v',
            'ts',
            'f4v',
            'swf',
            'vob',
            'asf',
        ];

        return implode(',', $extension);
    }

    protected function mimesImage(): string
    {
        $extension = [
            'gif',
            'jpeg',
            'png',
            'swf',
            'psd',
            'bmp',
            'jpg',
            'tiff',
            'tiff',
            'jpc',
            'jp2',
            'jpf',
            'jb2',
            'swc',
            'aiff',
            'wbmp',
            'xbm',
            'webp',
            'jfif',
        ];

        return implode(',', $extension);
    }

    protected function mimesImageOrVideo(): string
    {
        return $this->mimesImage().','.$this->mimesVideo();
    }

    protected function lat(): string
    {
        return 'numeric|between:-90,90';
    }

    protected function lng(): string
    {
        return 'numeric|between:-180,180';
    }

    protected function email(): string
    {
        return 'email|regex:/^[^\s@]+@[^\s@]+\.[^\s@]+$/|max:191';
    }

    /** @deprecated Use egyptianMobileRules() */
    protected function phone(): string
    {
        return 'digits:11';
    }

    protected function countryCode(): string
    {
        return 'numeric|digits_between:1,5';
    }

    public function password(): string
    {
        return 'required|min:6|max:100|confirmed';
    }

    /** @return list<string|object> */
    protected function egyptianNationalIdRules(bool $required = false): array
    {
        $rules = $required ? ['required'] : ['nullable'];

        return array_merge($rules, ['string', 'size:14', new EgyptianNationalId]);
    }

    /** @return list<string|object> */
    protected function egyptianMobileRules(bool $required = false): array
    {
        $rules = $required ? ['required'] : ['nullable'];

        return array_merge($rules, ['string', 'size:11', new EgyptianMobile]);
    }

    /** @return list<string> */
    protected function personNameRules(bool $required = true): array
    {
        $rules = $required ? ['required'] : ['nullable'];

        return array_merge($rules, ['string', 'min:2', 'max:255']);
    }

    /** @return list<string> */
    protected function qrCodeRules(bool $required = true): array
    {
        $rules = $required ? ['required'] : ['nullable'];

        return array_merge($rules, ['string', 'min:3', 'max:100', 'regex:/^[A-Za-z0-9\-_]+$/']);
    }

    /** @return list<string> */
    protected function barcodeRules(bool $required = true): array
    {
        $rules = $required ? ['required'] : ['nullable'];

        return array_merge($rules, ['string', 'min:1', 'max:100', 'regex:/^[A-Za-z0-9\-_]+$/']);
    }

    /** @return list<string> */
    protected function signedQtyRules(bool $required = true): array
    {
        $rules = $required ? ['required'] : ['nullable'];

        return array_merge($rules, ['integer', 'min:-999999', 'max:999999']);
    }

    /** @return list<string> */
    protected function positiveQtyRules(bool $required = true): array
    {
        $rules = $required ? ['required'] : ['nullable'];

        return array_merge($rules, ['integer', 'min:1', 'max:999999']);
    }

    /** @return list<string> */
    protected function moneyRules(bool $required = true): array
    {
        $rules = $required ? ['required'] : ['nullable'];

        return array_merge($rules, ['numeric', 'min:0.01', 'max:999999999.99']);
    }

    /** @return list<string> */
    protected function notesRules(int $max = 5000, bool $required = false): array
    {
        $rules = $required ? ['required'] : ['nullable'];

        return array_merge($rules, ['string', 'max:'.$max]);
    }
}
