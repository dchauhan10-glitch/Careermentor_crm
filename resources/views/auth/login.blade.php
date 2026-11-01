@extends('layouts.app')
@section('title', 'Log in')
@section('content')
<div class="login card">
  <h1>CareerMentors CRM</h1>
  <form method="post" action="{{ url('/login') }}">
    @csrf
    <label for="email">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required autocomplete="current-password">
    <p><button class="btn">Log in</button></p>
  </form>
  <p class="small muted">After 5 wrong tries, logging in is paused for a minute.</p>
</div>
@endsection
