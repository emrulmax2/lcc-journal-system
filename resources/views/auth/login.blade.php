{{--
    The one non-Inertia page in the app.

    Self-contained CSS on purpose: this view must render correctly whether or not Vite has
    built, and whether or not the Node SSR process is alive. Being locked out of the
    editorial office because an asset pipeline is down is not an acceptable failure mode.
    The tokens below are the design system's own — brand-700 teal, ink-900 navy, Newsreader
    over Inter.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,400;6..72,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-700: #0F766E;
            --brand-800: #115E59;
            --ink-50: #F8FAFC;
            --ink-200: #E2E8F0;
            --ink-300: #CBD5E1;
            --ink-500: #64748B;
            --ink-600: #475569;
            --ink-900: #0F172A;
            --danger-50: #FEF2F2;
            --danger-700: #B91C1C;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--ink-50);
            color: var(--ink-900);
            font-family: Inter, system-ui, sans-serif;
            padding: 2rem 1rem;
        }
        .card {
            width: 100%;
            max-width: 26rem;
            background: #fff;
            border: 1px solid var(--ink-200);
            border-radius: 1rem;
            padding: 2.5rem;
        }
        .eyebrow {
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--brand-700);
            margin: 0;
        }
        h1 {
            font-family: Newsreader, Georgia, serif;
            font-size: 2rem;
            font-weight: 600;
            margin: .75rem 0 .5rem;
        }
        p.lede { margin: 0 0 2rem; color: var(--ink-600); font-size: .9375rem; }
        label { display: block; font-size: .875rem; font-weight: 500; margin-bottom: .5rem; }
        input[type="email"], input[type="password"] {
            width: 100%;
            height: 3rem;
            padding: 0 1.25rem;
            margin-bottom: 1.25rem;
            border: 1px solid var(--ink-300);
            border-radius: 999px;
            font: inherit;
            color: inherit;
            background: #fff;
        }
        input:focus { outline: 2px solid var(--brand-700); outline-offset: 1px; border-color: var(--brand-700); }
        .remember { display: flex; align-items: center; gap: .5rem; margin-bottom: 1.5rem; font-size: .875rem; color: var(--ink-600); }
        .remember input { accent-color: var(--brand-700); }
        button {
            width: 100%;
            height: 3rem;
            border: 0;
            border-radius: 999px;
            background: var(--brand-700);
            color: #fff;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: var(--brand-800); }
        .errors {
            background: var(--danger-50);
            border: 1px solid var(--danger-700);
            border-radius: .75rem;
            padding: .875rem 1rem;
            margin-bottom: 1.5rem;
            color: var(--danger-700);
            font-size: .875rem;
        }
        .errors ul { margin: 0; padding-left: 1.25rem; }
        .back { display: block; margin-top: 1.5rem; text-align: center; font-size: .875rem; color: var(--ink-500); }
    </style>
</head>
<body>
    <main class="card">
        <p class="eyebrow">Editorial office</p>
        <h1>Sign in</h1>
        <p class="lede">Authors, reviewers and editors of {{ config('app.name') }}.</p>

        @if ($errors->any())
            {{-- role="alert" so a screen reader is told, not just shown. --}}
            <div class="errors" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   autocomplete="username" required autofocus>

            <label for="password">Password</label>
            <input id="password" name="password" type="password"
                   autocomplete="current-password" required>

            <label class="remember">
                <input type="checkbox" name="remember" value="1">
                Keep me signed in
            </label>

            <button type="submit">Sign in</button>
        </form>

        <a class="back" href="{{ route('home') }}">Back to {{ config('app.name') }}</a>
    </main>
</body>
</html>
