@extends('layouts.app')
@section('title', 'Log in')
@section('bare')
<div class="auth">
  <section class="panel">
    <div class="logo"><span class="mark"><img src="{{ asset('img/collegioz-mark.png') }}" alt="" width="30" height="30"></span><span><b>CareerMentors</b><small>Student Admission CRM</small>
      @unless (app()->environment('production'))<span class="env">TEST</span>@endunless</span></div>
    <h2>Every student, every call, in one place.</h2>
    <p>Track enquiries from your websites and lists, follow up on time, and see how each team is doing.</p>
    <ul>
      <li>Follow-ups for today and overdue calls</li>
      <li>Full call history for every student</li>
      <li>Sign-ups from your websites, automatically</li>
    </ul>
  </section>
  <section class="form">
    <div class="card">
      <h1>Welcome back</h1>
      <p class="muted" style="margin:0 0 8px">Log in with your work email.</p>
      @if ($errors->any())
        <div class="errors">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
      @endif
      @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
      <form method="post" action="{{ url('/login') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        <p style="margin:18px 0 8px"><button class="btn" style="width:100%">Log in</button></p>
      </form>
      <p class="small muted" style="margin:0">After 5 wrong tries, logging in is paused for a minute.</p>
    </div>
  </section>
</div>
@endsection
