<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'CRM') · CareerMentors CRM</title>
<link rel="stylesheet" href="{{ asset('css/crm.css') }}">
</head>
<body>
@auth
<nav class="top">
  <span class="brand">CareerMentors CRM</span>
  <a href="{{ route('dashboard') }}" @class(['on' => request()->routeIs('dashboard')])>Dashboard</a>
  <a href="{{ route('follow-ups') }}" @class(['on' => request()->routeIs('follow-ups')])>Follow-ups</a>
  <a href="{{ route('students.index') }}" @class(['on' => request()->routeIs('students.*')])>Students</a>
  @can('admin')
    <a href="{{ route('admin.users') }}" @class(['on' => request()->routeIs('admin.users')])>Staff &amp; teams</a>
    <a href="{{ route('admin.duplicates') }}" @class(['on' => request()->routeIs('admin.duplicates')])>Duplicates</a>
    <a href="{{ route('admin.imports.index') }}" @class(['on' => request()->routeIs('admin.imports.*')])>Import</a>
    <a href="{{ route('admin.websites') }}" @class(['on' => request()->routeIs('admin.websites')])>Websites</a>
    <a href="{{ route('admin.settings') }}" @class(['on' => request()->routeIs('admin.settings')])>Settings</a>
    <a href="{{ route('admin.backups') }}" @class(['on' => request()->routeIs('admin.backups')])>Backups</a>
  @endcan
  <form class="search" method="get" action="{{ route('students.index') }}">
    <input name="q" placeholder="Search mobile, name or ID" value="{{ request('q') }}" aria-label="Search students">
    <button class="btn small">Search</button>
  </form>
  <span class="who">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</span>
  <form method="post" action="{{ route('logout') }}">@csrf<button class="btn small light">Log out</button></form>
</nav>
@endauth
<main>
  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
  @if ($errors->any())
    <div class="errors">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
  @endif
  @yield('content')
</main>
</body>
</html>
