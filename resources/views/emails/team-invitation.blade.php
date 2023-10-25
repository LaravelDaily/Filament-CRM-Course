<x-mail::message>
You have been invited to join {{ config('app.name') }}

To accept the invitation - click on the button below and create an account:

<x-mail::button :url="$acceptUrl">
{{ __('Create Account') }}
</x-mail::button>

{{ __('If you did not expect to receive an invitation to this team, you may discard this email.') }}
</x-mail::message>
