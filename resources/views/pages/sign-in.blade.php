<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link
      rel="apple-touch-icon"
      sizes="76x76"
      href="{{ asset('assets/img/apple-icon.png') }}" />
    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}" />
    <title>Login KBSM</title>
    <link href="{{ asset('assets/auth.css') }}" rel="stylesheet" />
  </head>

  <body class="auth-page">
    <main class="auth-layout">
      <section class="auth-hero" aria-label="Koperasi KBSM">
        <img
          class="auth-hero__image"
          src="{{ asset('assets/img/login_page.png') }}"
          alt="KBSM — ATK, Konsinyasi, dan Simpan Pinjam" />
      </section>

      <section class="auth-panel">
        <div class="login-card">
          <header class="login-card__header">
            <div class="brand-lockup">
              <span class="brand-lockup__mark">
                <img
                  class="brand-lockup__logo"
                  src="{{ asset('assets/img/logo-koperasi.png') }}"
                  alt="Logo KBSM" />
              </span>
              <span class="brand-lockup__name">KBSM</span>
            </div>
            <h1>Selamat Datang Kembali</h1>
            <p>Masuk untuk melanjutkan ke dashboard KBSM.</p>
          </header>

          @if (session('success'))
            <div class="form-alert form-alert--success" role="status">
              {{ session('success') }}
            </div>
          @endif

          @if ($errors->any())
            <div class="form-alert form-alert--error" role="alert">
              <strong>Login belum berhasil.</strong>
              <ul>
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <form class="login-form" role="form" method="POST" action="{{ route('login.submit') }}">
            @csrf

            <div class="form-group">
              <label class="form-label" for="email">Email</label>
              <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                class="form-input @error('email') form-input--invalid @enderror"
                placeholder="nama@email.com"
                aria-label="Email"
                @error('email') aria-describedby="email-error" aria-invalid="true" @enderror />
              @error('email')
                <p class="form-error" id="email-error">{{ $message }}</p>
              @enderror
            </div>

            <div class="form-group">
              <label class="form-label" for="password">Password</label>
              <input
                id="password"
                type="password"
                name="password"
                class="form-input @error('password') form-input--invalid @enderror"
                placeholder="Masukkan password"
                aria-label="Password"
                @error('password') aria-describedby="password-error" aria-invalid="true" @enderror />
              @error('password')
                <p class="form-error" id="password-error">{{ $message }}</p>
              @enderror
            </div>

            <div class="remember-field">
              <input
                id="rememberMe"
                class="remember-field__input"
                type="checkbox"
                name="remember"
                value="1"
                {{ old('remember', true) ? 'checked' : '' }} />
              <label class="remember-field__label" for="rememberMe">Ingat saya</label>
            </div>

            <button type="submit" class="btn-login">Masuk</button>
          </form>

          <footer class="login-card__footer">
            Belum punya akun?
            <a href="{{ route('register') }}">Daftar sekarang</a>
          </footer>
        </div>
      </section>
    </main>
  </body>
</html>
