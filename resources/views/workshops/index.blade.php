@extends('layouts.dashboard')

@section('title', 'الورش')
@section('page', 'workshops')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>الورش</span></div>
        <h1>الورش</h1>
        <p>الورش المباشرة والحضورية، والمقاعد المحجوزة.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" data-open="workshopModal" data-requires="manage_content"><i data-icon="plus" class="sm"></i>ورشة جديدة</button>
      </div>
    </div>

    <div class="grid g4" id="wsStats"></div>

    <div class="card">
      <div class="toolbar">
        <div class="seg" id="wsSeg">
          <button class="on" data-s="upcoming">القادمة</button>
          <button data-s="past">المنتهية</button>
          <button data-s="all">الكل</button>
        </div>
      </div>
      <div class="card-body grid g2" id="wsList"></div>
    </div>
@endsection

@push('modals')
  <!-- create / edit modal -->
  <div class="modal" id="workshopModal" aria-hidden="true">
    <form class="modal-box" id="wsForm" novalidate>
      <div class="modal-head"><h3 id="wsModalTitle">ورشة جديدة</h3><button type="button" class="btn-icon" data-close aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="modal-body form-grid">
        <div class="field full"><label for="wTitle">عنوان الورشة *</label><input class="input" id="wTitle" required></div>
        <div class="field"><label for="wDate">التاريخ *</label><input class="input" id="wDate" type="date" required></div>
        <div class="field"><label for="wTime">الوقت</label><input class="input" id="wTime" type="time" value="19:00"></div>
        <div class="field"><label for="wFormat">النوع</label><select class="select" id="wFormat"><option value="online">أونلاين</option><option value="in-person">حضوري</option></select></div>
        <div class="field"><label for="wPlace">المكان / المنصة</label><input class="input" id="wPlace" value="Zoom"></div>
        <div class="field"><label for="wPrice">السعر ({{ trim($currencySymbol) }})</label><input class="input ltr" id="wPrice" type="number" min="0" value="0"></div>
        <div class="field"><label for="wSeats">عدد المقاعد *</label><input class="input ltr" id="wSeats" type="number" min="1" value="40" required></div>
        <div class="field full"><label for="wDesc">الوصف</label><textarea class="textarea" id="wDesc" rows="3" placeholder="ماذا سيبني المشاركون في الورشة؟"></textarea></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>إلغاء</button><button class="btn btn-primary" type="submit">حفظ الورشة</button></div>
    </form>
  </div>
@endpush

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/workshops.js') }}"></script>
@endpush
