<?php

namespace App\Http\Requests;

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
      'jfif'
    ];

    return implode(',', $extension);
  }

  protected function mimesImageOrVideo(): string
  {
    return $this->mimesImage() . ',' . $this->mimesVideo();
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

  protected function phone(): string
  {
    return 'digits:9';
  }

  protected function countryCode(): string
  {
    return 'numeric|digits_between:1,5';
  }

  public function password(): string
  {
    return 'required|min:6|max:100|confirmed';
  }

}
