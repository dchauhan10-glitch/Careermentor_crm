@extends('layouts.app')
@section('title', 'Log in')
@section('bare')
<div class="auth">
  <section class="panel">
    <div class="brandmark"><span class="mark"><img src="{{ asset('img/collegioz-mark.png') }}" alt="" width="28" height="28"></span><span><b>CareerMentors @unless (app()->environment('production'))<span class="env">TEST</span>@endunless</b><small>Student Admission CRM</small></span></div>
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
      <p class="muted mt-0 mb-2">Log in with your work email.</p>
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
        <p class="mt-6 mb-3"><button class="btn w-full">Log in</button></p>
      </form>
      <p class="small muted m-0">After 5 wrong tries, logging in is paused for a minute.</p>
    </div>
  </section>
</div>
@endsection
