@extends('layouts.dashboard')

@section('title', 'طلبات المشاريع')
@section('page', 'leads')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>طلبات المشاريع</span></div>
        <h1>طلبات المشاريع</h1>
        <p>اسحب البطاقة بين المراحل لتحديث حالة الطلب.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" data-open="leadModal"><i data-icon="plus" class="sm"></i>طلب جديد</button>
      </div>
    </div>

    <div class="grid g4" id="leadStats"></div>
    <div class="kanban" id="board"></div>
@endsection

@push('modals')
  <div class="modal" id="leadModal" aria-hidden="true">
    <form class="modal-box" id="leadForm" novalidate>
      <div class="modal-head"><h3>طلب مشروع جديد</h3><button type="button" class="btn-icon" data-close aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="modal-body form-grid">
        <div class="field"><label for="lName">اسم العميل *</label><input class="input" id="lName" required></div>
        <div class="field"><label for="lCompany">الجهة</label><input class="input" id="lCompany"></div>
        <div class="field"><label for="lService">الخدمة</label><select class="select" id="lService"><option>تطوير المواقع</option><option>تطبيقات الويب</option><option>المتاجر الإلكترونية</option><option>لوحات التحكم والأنظمة</option><option>الصيانة والتطوير المستمر</option><option>تدريب الفرق والجامعات</option></select></div>
        <div class="field"><label for="lBudget">الميزانية ($)</label><input class="input ltr" id="lBudget" type="number" min="0" value="1000"></div>
        <div class="field full"><label for="lNote">ملاحظات</label><textarea class="textarea" id="lNote" rows="3"></textarea></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>إلغاء</button><button class="btn btn-primary" type="submit">إضافة</button></div>
    </form>
  </div>
@endpush

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/leads.js') }}"></script>
@endpush
