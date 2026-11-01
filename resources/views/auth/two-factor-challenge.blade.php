@extends('layouts.app')
@section('title', 'Two-step login')
@section('content')
<div class="login card">
  <h1>Enter your 6-digit code</h1>
  <p class="muted">Open your authenticator app and type the code shown for CareerMentors CRM.</p>
  <form method="post" action="{{ url('/two-factor') }}">
    @csrf
    <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus>
    <p><button class="btn">Continue</button></p>
  </form>
</div>
@endsection
