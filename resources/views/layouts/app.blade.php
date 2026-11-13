<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#116d44">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="CRM">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="icon" type="image/png" href="{{ asset('img/collegioz-mark.png') }}">
<link rel="apple-touch-icon" href="{{ asset('img/app-icon-180.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<title>@yield('title', 'CRM') · CareerMentors CRM</title>
<link rel="stylesheet" href="{{ asset('css/crm.css') }}?v={{ @filemtime(public_path('css/crm.css')) }}">
</head>
@php($sidebarStyle = config('crm.sidebar') === 'dark' ? 'sb-dark' : 'sb-light')
<body class="{{ $sidebarStyle }}">
<script>try{if(localStorage.getItem('crm-side-mini')==='1')document.documentElement.classList.add('side-mini')}catch(e){}</script>
@auth
@php($me = auth()->user())
@php($nav = fn (string $route, string $pattern, string $icon, string $label, ?int $count = null) => ['route' => $route, 'on' => request()->routeIs($pattern), 'icon' => $icon, 'label' => $label, 'count' => $count])
@php($dueCount = \App\Models\Student::visibleTo($me)->where('next_follow_up_at', '<=', today()->endOfDay())->count())
@php($work = [
  $nav('dashboard', 'dashboard', 'home', 'Dashboard'),
  $nav('students.index', 'students.*', 'student', 'Leads'),
  $nav('follow-ups', 'follow-ups', 'calendar', 'Follow-ups', $dueCount ?: null),
  $nav('calls.index', 'calls.*', 'phone', 'Call History'),
])
@php($admin = [
  $nav('admin.users', 'admin.users', 'team', 'Users'),
  $nav('admin.imports.index', 'admin.imports.*', 'upload', 'Import lists'),
  $nav('admin.websites', 'admin.websites', 'globe', 'Websites'),
  $nav('admin.duplicates', 'admin.duplicates', 'copy', 'Duplicates'),
  $nav('admin.backups', 'admin.backups', 'shield', 'Backups'),
  $nav('admin.settings', 'admin.settings', 'settings', 'Settings'),
])
<input type="checkbox" id="nav-toggle" class="sr-only" aria-hidden="true" tabindex="-1">

<header class="appbar">
  <label for="nav-toggle" class="icon-only menu-btn" aria-label="Open menu">@include('partials.icon', ['name' => 'menu'])</label>
  <a class="brandmark" href="{{ route('dashboard') }}">
    <span class="mark"><img src="{{ asset('img/collegioz-mark.png') }}" alt="" width="28" height="28"></span>
    <span><b>CareerMentors @unless (app()->environment('production'))<span class="env">TEST</span>@endunless</b><small>Student Admission CRM</small></span>
  </a>
  <form class="appsearch" method="get" action="{{ route('students.index') }}" role="search">
    @include('partials.icon', ['name' => 'search'])
    <input name="q" placeholder="Search by student name or mobile" value="{{ request('q') }}" aria-label="Search leads" data-search>
    <kbd>/</kbd>
  </form>
  <div class="ml-auto flex items-center gap-2 sm:gap-3">
    <a class="icon-only lg:hidden" href="{{ route('students.index', ['focus' => 'search']) }}" aria-label="Search">@include('partials.icon', ['name' => 'search'])</a>
    <span class="clock" aria-label="Current time">@include('partials.icon', ['name' => 'clock'])<span data-clock>{{ now()->format('g:i A') }}</span></span>
    <div class="appuser">
      <span class="who"><b>{{ $me->name }}</b><span>{{ $me->role->label() }}@if ($me->team) · {{ $me->team->name }}@endif</span></span>
      <span class="avatar" aria-hidden="true">{{ collect(explode(' ', $me->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') }}</span>
      <form method="post" action="{{ route('logout') }}" class="m-0">@csrf
        <button class="logout" title="Log out">@include('partials.icon', ['name' => 'logout'])<span class="hidden sm:inline">Log out</span><span class="sr-only sm:hidden">Log out</span></button>
      </form>
    </div>
  </div>
</header>

<div class="frame">
  <label for="nav-toggle" class="scrim" aria-hidden="true"></label>
  <aside class="sidebar" aria-label="Menu">
    <nav aria-label="Main">
      <div class="group">Work</div>
      @foreach ($work as $n)
        <a href="{{ route($n['route']) }}" title="{{ $n['label'] }}" @class(['on' => $n['on']]) @if ($n['on']) aria-current="page" @endif>@include('partials.icon', ['name' => $n['icon']])<span class="lbl">{{ $n['label'] }}</span>@if ($n['count'])<span class="count" title="Due today or overdue">{{ number_format($n['count']) }}</span>@endif</a>
      @endforeach
      @can('admin')
        <div class="group">Admin</div>
        @foreach ($admin as $n)
          <a href="{{ route($n['route']) }}" title="{{ $n['label'] }}" @class(['on' => $n['on']]) @if ($n['on']) aria-current="page" @endif>@include('partials.icon', ['name' => $n['icon']])<span class="lbl">{{ $n['label'] }}</span></a>
        @endforeach
      @endcan
    </nav>
    <div class="foot">
      <span class="lbl">{{ $me->team?->name ?? $me->role->label() }}</span>
      <button type="button" class="collapse-btn" data-collapse title="Collapse menu" aria-label="Collapse menu">@include('partials.icon', ['name' => 'chevrons'])</button>
    </div>
  </aside>
  <div class="content">
    <main class="work">
      @if (session('status'))<div class="flash">@include('partials.icon', ['name' => 'check', 'class' => 'w-5 h-5'])<span>{{ session('status') }}</span></div>@endif
      @if ($errors->any())
        <div class="errors">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
      @endif
      @yield('content')
    </main>
  </div>
</div>

<nav class="tabbar" aria-label="Quick menu">
  @foreach ($work as $n)
    <a href="{{ route($n['route']) }}" @class(['on' => $n['on']]) @if ($n['on']) aria-current="page" @endif>@include('partials.icon', ['name' => $n['icon']])<span>{{ $n['label'] === 'Call History' ? 'Calls' : $n['label'] }}</span></a>
  @endforeach
</nav>
@else
@hasSection('bare')
@yield('bare')
@else
<main class="work max-w-xl">
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
  var cs=document.querySelectorAll('[data-clock]'),d=document.querySelector('[data-date]');
  function tick(){var n=new Date();
    var t=n.toLocaleTimeString('en-IN',{hour:'numeric',minute:'2-digit',hour12:true}).toUpperCase();cs.forEach(function(c){c.textContent=t;});
    if(d)d.textContent=n.toLocaleDateString('en-IN',{weekday:'long',day:'numeric',month:'long',year:'numeric'});}
  if(cs.length){tick();setInterval(tick,15000);}
  var cb=document.querySelector('[data-collapse]');
  if(cb)cb.addEventListener('click',function(){var m=document.documentElement.classList.toggle('side-mini');
    try{localStorage.setItem('crm-side-mini',m?'1':'0')}catch(e){}
    cb.title=m?'Expand menu':'Collapse menu';cb.setAttribute('aria-label',cb.title);});
  var s=document.querySelector('[data-search]');
  document.addEventListener('keydown',function(e){var t=e.target.tagName;
    if(s&&e.key==='/'&&t!=='INPUT'&&t!=='TEXTAREA'&&t!=='SELECT'){e.preventDefault();s.focus();}});
  if('serviceWorker' in navigator && location.protocol==='https:'){navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(function(){});}
})();
</script>
</body>
</html>
