@component('mail::message')
# {{ trans('notifications.schedule.new.mail.title') }}

{{ $content }}

Mit freundlichen Grüßen,<br>
{{ Config::get('setting.app_name') }}

@include('notifications.partials.subscription')

@endcomponent