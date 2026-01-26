@component('mail::message')
# {{ $type === 'email_verification' ? 'Verify Your Email' : 'Reset Your Password' }}

Dear User,

{{ $intro }}

<div style="text-align: center; margin: 30px 0;">
    <span style="font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #1a1a1a; background: #f5f5f5; padding: 20px 40px; border-radius: 8px; display: inline-block;">
        {{ $otp }}
    </span>
</div>

This code will expire in <strong>{{ $expirationMinutes }} minutes</strong>.

@if ($type === 'email_verification')
Once verified, you'll be able to access all features of QashierWise.
@else
If you didn't request this code, please ignore this email and your password will remain unchanged.
@endif

Thanks,<br>
{{ config('app.name') }}

@component('mail::button', ['url' => config('app.url')])
Visit QashierWise
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
