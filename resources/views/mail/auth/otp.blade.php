<x-mail::message>
<strong>Hi {{ $user->name }},</strong>

Your 6-digit code is:

<strong>{{$otp->code}}</strong>

@if ($otp->type == 'password-reset')
<p>Use this code to reset your password in the app.</p>
@else
<p>Use this code to complete the verification process in the app.</p>
@endif

Do not share this code. MONET representatives will never reach out to you to verify this code over the phone or your email app.

<strong>The code is valid for 10 minutes.</strong>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>