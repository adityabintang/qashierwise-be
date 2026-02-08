@component('mail::message')
# Reset Your Password

Dear User,

We received a request to reset your password for your QashierWise account. Click the button below to create a new password:

@component('mail::button', ['url' => $resetUrl])
Reset Password
@endcomponent

This link will expire in <strong>{{ $expirationMinutes }} minutes</strong>.

If you didn't request this password reset, please ignore this email and your password will remain unchanged.

Thanks,<br>
{{ config('app.name') }}

@component('mail::button', ['url' => config('app.url')])
Visit QashierWise
@endcomponent
@endcomponent
