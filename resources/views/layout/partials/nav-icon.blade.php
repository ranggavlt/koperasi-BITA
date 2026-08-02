@props(['icon' => 'circle'])

@php
  $name = (string) $icon;
@endphp

<svg class="kbsm-sidebar-svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
  @switch($name)
    @case('dashboard')
      <path d="M3 4h8v8H3V4Zm10 0h8v5h-8V4ZM3 14h8v6H3v-6Zm10-3h8v9h-8v-9Z" />
      @break
    @case('pos')
      <path d="M5 4h14a2 2 0 0 1 2 2v9H3V6a2 2 0 0 1 2-2Zm1 3v2h7V7H6Zm11 0v2h2V7h-2ZM4 17h16v2H4v-2Z" />
      @break
    @case('cash')
      <path d="M3 6h18v12H3V6Zm2 3v6h14V9H5Zm4 1h6a2 2 0 1 1 0 4H9v-2h6v-1H9v-1Z" />
      @break
    @case('report')
      <path d="M4 3h16v18H4V3Zm3 4v10h2V7H7Zm4 5v5h2v-5h-2Zm4-3v8h2V9h-2Z" />
      @break
    @case('users')
    @case('employee')
    @case('member')
    @case('members')
      <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 2-8 4.5V21h16v-2.5C20 16 16.42 14 12 14Z" />
      @break
    @case('management')
      <path d="M12 3a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm-7 15c0-2.76 3.13-5 7-5s7 2.24 7 5v1H5v-1Zm14-9h4v2h-4V9Zm0 4h4v2h-4v-2Z" />
      @break
    @case('master')
      <path d="M4 4h16v4H4V4Zm0 6h7v10H4V10Zm9 0h7v10h-7V10Z" />
      @break
    @case('savings')
      <path d="M12 3c-4.42 0-8 2.02-8 4.5V16c0 2.76 3.58 5 8 5s8-2.24 8-5V7.5C20 5.02 16.42 3 12 3Zm0 2c3.31 0 6 1.12 6 2.5S15.31 10 12 10 6 8.88 6 7.5 8.69 5 12 5Zm0 8c2.39 0 4.53-.63 6-1.61V16c0 1.38-2.69 3-6 3s-6-1.62-6-3v-4.61C7.47 12.37 9.61 13 12 13Z" />
      @break
    @case('loan')
    @case('installment')
      <path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 2v10h16V7H4Zm3 3h6v2H7v-2Zm0 4h10v2H7v-2Z" />
      @break
    @case('wallet')
    @case('mutation')
      <path d="M3 6h15a3 3 0 0 1 3 3v9H3V6Zm2 2v8h14v-2h-5a3 3 0 0 1 0-6h5a1 1 0 0 0-1-1H5v1Zm9 2a1 1 0 0 0 0 2h5v-2h-5Z" />
      @break
    @case('calendar')
    @case('payroll')
      <path d="M7 2h2v2h6V2h2v2h3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h3V2Zm13 8H4v10h16V10Z" />
      @break
    @case('reconcile')
      <path d="M4 5h10v2H4V5Zm0 4h16v2H4V9Zm0 4h10v2H4v-2Zm0 4h16v2H4v-2Zm14-13 4 4-1.4 1.4L18 6.8l-4.6 4.6L12 10l6-6Z" />
      @break
    @case('car')
      <path d="m5 11 1.4-4.2A2 2 0 0 1 8.3 5h7.4a2 2 0 0 1 1.9 1.8L19 11h1a1 1 0 0 1 1 1v5h-2a2 2 0 1 1-4 0H9a2 2 0 1 1-4 0H3v-5a1 1 0 0 1 1-1h1Zm2.8-4-1.1 4h10.6l-1.1-4H7.8Z" />
      @break
    @case('hardware')
    @case('printer')
      <path d="M6 3h12v5H6V3Zm-2 7h16a2 2 0 0 1 2 2v5h-4v4H6v-4H2v-5a2 2 0 0 1 2-2Zm4 6v3h8v-5H8v2Zm10-3h2v-2h-2v2Z" />
      @break
    @case('invoice')
      <path d="M6 2h9l5 5v15H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.5V8h4.5L14 3.5ZM7 11h10v2H7v-2Zm0 4h10v2H7v-2Z" />
      @break
    @case('operations')
      <path d="M12 2 3 6v12l9 4 9-4V6l-9-4Zm0 2.2L18.5 7 12 9.8 5.5 7 12 4.2ZM5 9.2l6 2.6v7.7l-6-2.7V9.2Zm8 10.3v-7.7l6-2.6v7.6l-6 2.7Z" />
      @break
    @case('ledger')
    @case('journal')
    @case('book')
      <path d="M5 3h14a2 2 0 0 1 2 2v16H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 2v12h14V5H5Zm2 3h10v2H7V8Zm0 4h7v2H7v-2Z" />
      @break
    @case('history')
      <path d="M12 4V1L7 6l5 5V8a6 6 0 1 1-5.2 3H4.6A8 8 0 1 0 12 4Zm-1 6h2v5h-2v-5Zm0 6h2v2h-2v-2Z" />
      @break
    @case('tag')
    @case('box')
    @case('partner')
    @case('vendor')
      <path d="M20.59 13.41 11 3.83V2H3v8l9.59 9.59a2 2 0 0 0 2.82 0l5.18-5.18a2 2 0 0 0 0-2.82ZM7 8a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z" />
      @break
    @default
      <path d="M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20Zm0 5a5 5 0 1 0 0 10 5 5 0 0 0 0-10Z" />
  @endswitch
</svg>
