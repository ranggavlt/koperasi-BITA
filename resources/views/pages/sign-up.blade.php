<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="apple-touch-icon" sizes="76x76" href="{{ asset('assets/img/apple-icon.png') }}" />
    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}" />
    <title>Register</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link href="{{ asset('assets/css/nucleo-icons.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/nucleo-svg.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/soft-ui-dashboard-tailwind.css') }}?v=1.0.5" rel="stylesheet" />
  </head>

  <body class="m-0 font-sans antialiased font-normal bg-gray-50 text-start text-base leading-default text-slate-500">
    <main class="min-h-screen flex items-center justify-center px-4 py-10">
      <div class="w-full max-w-md">
        <div class="relative flex flex-col min-w-0 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
          <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl text-center">
            <h5 class="mb-1">Buat Akun</h5>
            <p class="text-sm text-slate-400 mb-0">Registrasi akun koperasi.</p>
          </div>

          <div class="flex-auto p-6">
            @if (session('success'))
              <div class="mb-4 rounded-lg bg-green-100 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
              </div>
            @endif

            @if ($errors->any())
              <div class="mb-4 rounded-lg bg-red-100 px-4 py-3 text-sm text-red-700">
                <ul class="mb-0 list-disc pl-5">
                  @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                  @endforeach
                </ul>
              </div>
            @endif

            <form role="form" method="POST" action="{{ route('register.submit') }}">
              @csrf

              <label class="mb-2 ml-1 font-bold text-xs text-slate-700">Nama</label>
              <div class="mb-4">
                <input
                  type="text"
                  name="name"
                  value="{{ old('name') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none focus:transition-shadow"
                  placeholder="Nama lengkap" />
              </div>

              <label class="mb-2 ml-1 font-bold text-xs text-slate-700">Email</label>
              <div class="mb-4">
                <input
                  type="email"
                  name="email"
                  value="{{ old('email') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none focus:transition-shadow"
                  placeholder="email@example.com" />
              </div>

              <label class="mb-2 ml-1 font-bold text-xs text-slate-700">Password</label>
              <div class="mb-4">
                <input
                  type="password"
                  name="password"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none focus:transition-shadow"
                  placeholder="Password" />
              </div>

              <label class="mb-2 ml-1 font-bold text-xs text-slate-700">Konfirmasi Password</label>
              <div class="mb-4">
                <input
                  type="password"
                  name="password_confirmation"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none focus:transition-shadow"
                  placeholder="Ulangi password" />
              </div>

              <label class="mb-2 ml-1 font-bold text-xs text-slate-700">Kode Keuangan (opsional)</label>
              <div class="mb-4">
                <input
                  type="text"
                  name="kode_keuangan"
                  value="{{ old('kode_keuangan') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none focus:transition-shadow"
                  placeholder="Isi jika daftar sebagai Keuangan" />
              </div>

              <div class="text-center">
                <button
                  type="submit"
                  class="inline-block w-full px-6 py-3 mt-2 mb-0 font-bold text-center text-white uppercase align-middle transition-all bg-transparent border-0 rounded-lg cursor-pointer shadow-soft-md bg-x-25 bg-150 leading-pro text-xs ease-soft-in tracking-tight-soft bg-gradient-to-tl from-gray-900 to-slate-800 hover:scale-102 hover:shadow-soft-xs active:opacity-85">
                  Daftar
                </button>
              </div>
            </form>

            <p class="mt-4 mb-0 leading-normal text-sm text-center">
              Sudah punya akun?
              <a href="{{ route('login') }}" class="font-bold text-slate-700">Login</a>
            </p>
          </div>
        </div>
      </div>
    </main>
  </body>
</html>

