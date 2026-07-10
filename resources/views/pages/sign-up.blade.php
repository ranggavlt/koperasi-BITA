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
    <title>Registrasi KBSM</title>
    <link href="{{ asset('assets/auth.css') }}" rel="stylesheet" />
  </head>

  <body class="auth-page">
    <main class="auth-layout">
      <section class="auth-hero" aria-label="Koperasi KBSM">
        <img
          class="auth-hero__image"
          src="{{ asset('assets/img/registrasi_page.png') }}"
          alt="KBSM — ATK, Konsinyasi, dan Simpan Pinjam" />
      </section>

      <section class="auth-panel auth-panel--register">
        <div class="login-card register-card">
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
            <h1>Buat Akun KBSM</h1>
            <p>Lengkapi data berikut untuk membuat akun koperasi.</p>
          </header>

          @if (session('success'))
            <div class="form-alert form-alert--success" role="status">
              {{ session('success') }}
            </div>
          @endif

          @if ($errors->any())
            <div class="form-alert form-alert--error" role="alert">
              <strong>Registrasi belum berhasil.</strong>
              <ul>
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <form
            class="login-form register-form"
            role="form"
            method="POST"
            action="{{ route('register.submit') }}">
            @csrf

            <div class="form-group">
              <label class="form-label" for="name">Nama</label>
              <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name') }}"
                class="form-input @error('name') form-input--invalid @enderror"
                placeholder="Nama lengkap"
                autocomplete="name"
                @error('name') aria-describedby="name-error" aria-invalid="true" @enderror />
              @error('name')
                <p class="form-error" id="name-error">{{ $message }}</p>
              @enderror
            </div>

            <div class="form-group">
              <label class="form-label" for="email">Email</label>
              <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                class="form-input @error('email') form-input--invalid @enderror"
                placeholder="nama@email.com"
                autocomplete="email"
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
                placeholder="Minimal 8 karakter"
                autocomplete="new-password"
                @error('password') aria-describedby="password-error" aria-invalid="true" @enderror />
              @error('password')
                <p class="form-error" id="password-error">{{ $message }}</p>
              @enderror
            </div>

            <div class="form-group">
              <label class="form-label" for="password_confirmation">Konfirmasi Password</label>
              <input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                class="form-input"
                placeholder="Ulangi password"
                autocomplete="new-password" />
            </div>

            <div class="form-group">
              <label class="form-label" for="kode_keuangan">
                Kode Keuangan <span class="form-label__optional">(opsional)</span>
              </label>
              <input
                id="kode_keuangan"
                type="text"
                name="kode_keuangan"
                value="{{ old('kode_keuangan') }}"
                class="form-input @error('kode_keuangan') form-input--invalid @enderror"
                placeholder="Isi jika mendaftar sebagai Keuangan"
                @error('kode_keuangan') aria-describedby="kode-keuangan-error" aria-invalid="true" @enderror />
              @error('kode_keuangan')
                <p class="form-error" id="kode-keuangan-error">{{ $message }}</p>
              @enderror
            </div>

            <button type="submit" class="btn-login">Daftar</button>
          </form>

          <footer class="login-card__footer">
            Sudah punya akun?
            <a href="{{ route('login') }}">Masuk</a>
          </footer>
        </div>
      </section>
    </main>
  </body>
</html>
