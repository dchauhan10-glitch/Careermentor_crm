<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#116d44">
<title>@yield('title', 'CRM') · CareerMentors CRM</title>
<link rel="stylesheet" href="{{ asset('css/crm.css') }}?v=3">
</head>
<body>
@auth
@php($me = auth()->user())
@php($nav = fn (string $route, string $pattern, string $icon, string $label) => ['route' => $route, 'on' => request()->routeIs($pattern), 'icon' => $icon, 'label' => $label])
@php($work = [
  $nav('dashboard', 'dashboard', 'home', 'Dashboard'),
  $nav('follow-ups', 'follow-ups', 'calendar', 'Follow-ups'),
  $nav('students.index', 'students.*', 'student', 'Students'),
])
@php($admin = [
  $nav('admin.users', 'admin.users', 'team', 'Staff & teams'),
  $nav('admin.duplicates', 'admin.duplicates', 'copy', 'Duplicates'),
  $nav('admin.imports.index', 'admin.imports.*', 'upload', 'Import lists'),
  $nav('admin.websites', 'admin.websites', 'globe', 'Websites'),
  $nav('admin.settings', 'admin.settings', 'settings', 'Settings'),
  $nav('admin.backups', 'admin.backups', 'shield', 'Backups'),
])
<input type="checkbox" id="nav-toggle" class="sr-only" aria-hidden="true">
<div class="shell">
  <label for="nav-toggle" class="scrim" aria-hidden="true"></label>
  <aside class="sidebar">
    <a class="logo" href="{{ route('dashboard') }}">
      <span class="mark">CM</span>
      <span><b>CareerMentors</b><small>Student Admission CRM</small>
        @unless (app()->environment('production'))<span class="env">TEST</span>@endunless</span>
    </a>
    <nav aria-label="Main">
      <div class="group">Work</div>
      @foreach ($work as $n)
        <a href="{{ route($n['route']) }}" @class(['on' => $n['on']])>@include('partials.icon', ['name' => $n['icon']]){{ $n['label'] }}</a>
      @endforeach
      @can('admin')
        <div class="group">Admin</div>
        @foreach ($admin as $n)
          <a href="{{ route($n['route']) }}" @class(['on' => $n['on']])>@include('partials.icon', ['name' => $n['icon']]){{ $n['label'] }}</a>
        @endforeach
      @endcan
    </nav>
    <div class="foot">{{ $me->team?->name ?? $me->role->label() }}</div>
  </aside>
  <div>
    <header class="topbar">
      <label for="nav-toggle" class="menu-btn" aria-label="Open menu">@include('partials.icon', ['name' => 'menu'])</label>
      @php($h = now()->hour)
      <div class="greet">
        <b>Good {{ $h < 12 ? 'morning' : ($h < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', $me->name)[0] }}</b>
        <span data-date>{{ now()->format('l, j F Y') }}</span>
      </div>
      <div class="clock" aria-label="Current time">@include('partials.icon', ['name' => 'clock'])<span data-clock>{{ now()->format('g:i A') }}</span></div>
      <details class="search-pop">
        <summary class="icon-btn" title="Search students (press /)">@include('partials.icon', ['name' => 'search'])<span class="sr-only">Search</span></summary>
        <form class="search" method="get" action="{{ route('students.index') }}" role="search">
          @include('partials.icon', ['name' => 'search'])
          <input name="q" placeholder="Search by mobile, name or student ID" value="{{ request('q') }}" aria-label="Search students">
        </form>
      </details>
      <div class="user">
        <span class="avatar" aria-hidden="true">{{ collect(explode(' ', $me->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') }}</span>
        <span class="who"><b>{{ $me->name }}</b><span>{{ $me->role->label() }}</span></span>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn small light" title="Log out">@include('partials.icon', ['name' => 'logout'])<span class="sr-only">Log out</span></button></form>
      </div>
    </header>
    <main>
      @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
      @if ($errors->any())
        <div class="errors">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
      @endif
      @yield('content')
    </main>
  </div>
</div>
@else
@hasSection('bare')
@yield('bare')
@else
<main>
  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
  @if ($errors->any())
    <div class="errors">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
  @endif
  @yield('content')
</main>
@endif
@endauth
<script>
(function(){
  var c=document.querySelector('[data-clock]'),d=document.querySelector('[data-date]');
  function tick(){var n=new Date();
    if(c)c.textContent=n.toLocaleTimeString('en-IN',{hour:'numeric',minute:'2-digit',second:'2-digit',hour12:true}).toUpperCase();
    if(d)d.textContent=n.toLocaleDateString('en-IN',{weekday:'long',day:'numeric',month:'long',year:'numeric'});}
  if(c){tick();setInterval(tick,1000);}
  var pop=document.querySelector('.search-pop');
  if(pop){pop.addEventListener('toggle',function(){if(pop.open)pop.querySelector('input').focus();});
    document.addEventListener('keydown',function(e){var t=e.target.tagName;
      if(e.key==='/'&&t!=='INPUT'&&t!=='TEXTAREA'&&t!=='SELECT'){e.preventDefault();pop.open=true;}
      if(e.key==='Escape')pop.open=false;});}
})();
</script>
</body>
</html>
