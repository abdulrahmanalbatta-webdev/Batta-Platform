@extends('layouts.dashboard')

@section('title', 'الرسائل')
@section('page', 'messages')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>الرسائل</span></div>
        <h1>الرسائل</h1>
        <p>استفسارات الطلاب والعملاء في مكان واحد.</p>
      </div>
    </div>

    <div class="card chat" id="chat">
      <aside class="chat-list">
        <label class="search"><i data-icon="search" class="sm"></i><input id="tq" type="search" placeholder="ابحث في المحادثات…" aria-label="بحث في المحادثات"></label>
        <div class="seg" id="threadSeg" style="margin:0 14px 10px;width:fit-content">
          <button class="on" data-f="all">الكل</button><button data-f="unread">غير مقروءة</button>
        </div>
        <div class="threads" id="threads"></div>
      </aside>
      <section class="chat-main" id="chatMain"></section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/messages.js') }}"></script>
@endpush
