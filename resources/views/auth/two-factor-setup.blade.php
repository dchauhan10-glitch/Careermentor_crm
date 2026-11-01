@extends('layouts.app')
@section('title', 'Set up two-step login')
@section('content')
<div class="card" style="max-width:560px">
  <h1>Set up two-step login</h1>
  <p>As Super Admin, you need a code from your phone as well as your password.</p>
  <ol>
    <li>Install <b>Google Authenticator</b> or <b>Microsoft Authenticator</b> on your phone.</li>
    <li>In the app, choose <b>Add account</b>, then <b>Enter a setup key</b>.</li>
    <li>Account name: <b>CareerMentors CRM</b>. Key: <code style="font-size:17px">{{ $secret }}</code> (time-based).</li>
    <li>Type the 6-digit code the app shows below.</li>
  </ol>
  <form method="post" action="{{ route('two-factor.confirm') }}">
    @csrf
    <label for="code">6-digit code</label>
    <input id="code" name="code" inputmode="numeric" maxlength="7" required style="max-width:200px">
    <p><button class="btn">Turn on two-step login</button></p>
  </form>
  <p class="small muted">Keep the setup key somewhere safe. If you lose your phone, it lets you add the account again.</p>
</div>
@endsection
