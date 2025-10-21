/* javasc.js  |  منطق لوحة الأدمن بالكامل بتعليقات مبسطة */

/* ========== 1) ترجمة بسيطة (i18n) ========== */
const i18n = {
  ar:{
    title:'لوحة الأدمن — مِعمار', brand:'مِعمار — لوحة الأدمن',
    kpi_users:'مستخدمون نشطون', kpi_contractors:'مقاولون موثّقون', kpi_pending:'طلبات تحقق معلّقة', kpi_complaints:'شكاوى مفتوحة',
    tab_users:'المستخدمون', tab_verify:'التحقّق', tab_complaints:'الشكاوى', tab_reports:'التقارير', tab_notifs:'الإشعارات', tab_settings:'الإعدادات',
    users_title:'إدارة المستخدمين', search_users_ph:'ابحث بالاسم أو البريد', any_role:'أي دور',
    role_client:'عميل', role_contractor:'مقاول', role_admin:'أدمن',
    th_name:'الاسم', th_email:'البريد', th_role:'الدور', th_status:'الحالة', th_created:'تاريخ التسجيل', th_actions:'إجراءات',
    btn_deactivate:'إيقاف', btn_activate:'تفعيل', btn_promote:'ترقية', btn_demote:'خفض', btn_edit:'تعديل', btn_delete:'حذف',
    verify_title:'طلبات التحقّق من المقاولين', verify_hint:'راجعي المستندات ثم وافقي أو ارفضي.', btn_approve:'قبول', btn_reject:'رفض',
    complaints_title:'الشكاوى', th_id:'#', th_by:'من', th_against:'ضد', th_project:'المشروع',
    reports_title:'التقارير', from:'من', to:'إلى', export_csv:'تصدير CSV', r_users:'ملخّص المستخدمين', r_projects:'ملخّص المشروعات',
    notifs_title:'إعدادات الإشعارات', n_email:'تنبيهات البريد', n_push:'تنبيهات فورية (Push)', n_sms:'رسائل SMS', save:'حفظ',
    settings_title:'الإعدادات', name:'الاسم', email:'البريد', timezone:'المنطقة الزمنية', security:'الأمان', enable_2fa:'تفعيل 2FA',
    toggle_title:'تبديل اللغة',
    status_active:'نشط', status_inactive:'غير نشط', status_suspended:'موقوف', status_pending:'قيد الانتظار',
    profile_title:'الملف الشخصي', profile_info:'معلوماتي', btn_logout:'تسجيل الخروج'
  },
  en:{
    title:'Admin Dashboard — Mimar', brand:'Mimar — Admin',
    kpi_users:'Active users', kpi_contractors:'Verified contractors', kpi_pending:'Pending verifications', kpi_complaints:'Open complaints',
    tab_users:'Users', tab_verify:'Verification', tab_complaints:'Complaints', tab_reports:'Reports', tab_notifs:'Notifications', tab_settings:'Settings',
    users_title:'User management', search_users_ph:'Search by name or email', any_role:'Any role',
    role_client:'Client', role_contractor:'Contractor', role_admin:'Admin',
    th_name:'Name', th_email:'Email', th_role:'Role', th_status:'Status', th_created:'Registration Date', th_actions:'Actions',
    btn_deactivate:'Deactivate', btn_activate:'Activate', btn_promote:'Promote', btn_demote:'Demote', btn_edit:'Edit', btn_delete:'Delete',
    verify_title:'Contractor verification requests', verify_hint:'Review documents then approve or reject.', btn_approve:'Approve', btn_reject:'Reject',
    complaints_title:'Complaints', th_id:'#', th_by:'By', th_against:'Against', th_project:'Project',
    reports_title:'Reports', from:'From', to:'To', export_csv:'Export CSV', r_users:'Users summary', r_projects:'Projects summary',
    notifs_title:'Notification settings', n_email:'Email alerts', n_push:'Push notifications', n_sms:'SMS messages', save:'Save',
    settings_title:'Settings', name:'Name', email:'Email', timezone:'Timezone', security:'Security', enable_2FA:'Enable 2FA',
    toggle_title:'Toggle language',
    status_active:'Active', status_inactive:'Inactive', status_suspended:'Suspended', status_pending:'Pending',
    profile_title:'Profile', profile_info:'My info', btn_logout:'Log out'
  }
};

/* دالتان مساعدتان للترجمة */
function t(k){ const lang=localStorage.getItem('langAdmin')||'ar'; return (i18n[lang]&&i18n[lang][k])||k; }
function applyI18n(lang){
  const d=i18n[lang]||i18n.ar;

  // تغيير لغة/اتجاه الصفحة + عنوانها
  document.documentElement.lang = (lang==='en'?'en':'ar');
  document.documentElement.dir  = (lang==='en'?'ltr':'rtl');
  document.title = d.title;

  // تغيير نص زر اللغة (EN<->AR)
  const label = document.getElementById('langLabel');
  if(label) label.textContent = (lang==='ar'?'EN':'AR');

  // استبدال كل العناصر التي تحمل data-i18n
  document.querySelectorAll('[data-i18n]').forEach(el=>{
    const key=el.getAttribute('data-i18n');
    if(d[key]!==undefined) el.textContent=d[key];
  });
  // استبدال خصائص placeholder… لو موجودة
  document.querySelectorAll('[data-i18n-attr]').forEach(el=>{
    el.getAttribute('data-i18n-attr').split(',').forEach(pair=>{
      const [attr,key]=pair.split(':').map(s=>s.trim());
      if(d[key]!==undefined) el.setAttribute(attr,d[key]);
    });
  });

  localStorage.setItem('langAdmin',lang);

  // تحديث شارة الحالة بعد تغيير اللغة
  setStatusUI(adminStatus);
}

/* ========== 2) حالة الأدمن الحالية (لتلوين الشارة) ========== */
let adminStatus = 'active';

/* ========== 3) أدوات عرض مبسطة ========== */
/* تحديث الشارة داخل اللوح الجانبي */
function setStatusUI(status){
  const el = document.getElementById('pStatusChip');
  if(!el) return;
  const isInactive = status === 'inactive';
  el.textContent = isInactive ? t('status_inactive') : t('status_active');
  el.className = 'status ' + (isInactive ? 's-inactive' : 's-active');
}

/* ========== 4) اللوح الجانبي (فتح/إغلاق + مزامنة) ========== */
const drawer   = document.getElementById('profileDrawer');
const openBtn  = document.getElementById('openProfile');
const closeBtn = document.getElementById('closeDrawer');
const overlay  = document.getElementById('drawerOverlay');

function openDrawer(){
  // نقرأ من تبويب الإعدادات لو فيه قيم مُعدّلة
  const name  = document.getElementById('setName')?.value || document.getElementById('adminName')?.textContent || 'Admin';
  const email = document.getElementById('setEmail')?.value || document.getElementById('profileEmail')?.textContent || 'admin@example.com';
  const twofa = document.getElementById('set2fa')?.checked || false;

  // نحقن القيم داخل اللوح
  document.getElementById('pName').value = name;
  document.getElementById('pEmail').value = email;
  document.getElementById('profileEmail').textContent = email;
  document.querySelector('.drawer .avatar').textContent = (name||'A').charAt(0).toUpperCase();
  document.getElementById('p2fa').checked = twofa;

  // شارة الحالة
  setStatusUI(adminStatus);

  // إظهار اللوح
  drawer.classList.add('open');
  drawer.setAttribute('aria-hidden','false');
}

function closeDrawer(){
  drawer.classList.remove('open');
  drawer.setAttribute('aria-hidden','true');
}

// ربط أزرار اللوح
openBtn?.addEventListener('click', openDrawer);
closeBtn?.addEventListener('click', closeDrawer);
overlay?.addEventListener('click', closeDrawer);
document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeDrawer(); });

// حفظ من داخل اللوح (ينسخ القيم إلى تبويب الإعدادات)
document.getElementById('saveProfile')?.addEventListener('click', ()=>{
  const name  = document.getElementById('pName').value.trim();
  const email = document.getElementById('pEmail').value.trim();

  if(document.getElementById('setName'))  document.getElementById('setName').value  = name;
  if(document.getElementById('setEmail')) document.getElementById('setEmail').value = email;
  if(document.getElementById('set2fa'))   document.getElementById('set2fa').checked = document.getElementById('p2fa').checked;

  // تحديث الاسم/الحرف في الهيدر
  document.getElementById('adminName').textContent = name || 'Admin';
  document.querySelector('header .avatar').textContent = (name||'A').charAt(0).toUpperCase();
  alert('✓ تم الحفظ');
});

// زر "اذهب للإعدادات"
document.getElementById('gotoSettings')?.addEventListener('click', ()=>{
  // Switch to settings tab
  document.querySelectorAll('.tab').forEach(t => t.setAttribute('aria-selected', 'false'));
  document.querySelector('[data-tab="settings"]').setAttribute('aria-selected', 'true');
  
  document.querySelectorAll('.panel').forEach(panel => {
    panel.classList.remove('active');
    if (panel.id === 'panel-settings') {
      panel.classList.add('active');
    }
  });
  
  closeDrawer();
});

// سويتش 2FA داخل اللوح (تجريبي)
document.getElementById('p2fa')?.addEventListener('change', (e)=>{
  alert(e.target.checked ? 'تم تفعيل التحقق بخطوتين (تجريبي)' : 'تم إيقاف التحقق بخطوتين (تجريبي)');
});

/* ========== 5) أحداث عامة (تبويبات/تصفية) ========== */
document.addEventListener('DOMContentLoaded', function() {
  // التبويبات
  const tabs = document.querySelectorAll('.tab');
  const panels = document.querySelectorAll('.panel');

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const targetTab = tab.getAttribute('data-tab');
      
      // Update tabs
      tabs.forEach(t => t.setAttribute('aria-selected', 'false'));
      tab.setAttribute('aria-selected', 'true');
      
      // Update panels
      panels.forEach(panel => {
        panel.classList.remove('active');
        if (panel.id === 'panel-' + targetTab) {
          panel.classList.add('active');
        }
      });
    });
  });

  // زر تبديل اللغة
  document.getElementById('langToggle').addEventListener('click', ()=>{
    const current = localStorage.getItem('langAdmin') || 'ar';
    applyI18n(current==='ar' ? 'en' : 'ar');
  });

  // حفظ إعدادات عامة (تحديث اسم الهيدر فوراً)
  document.getElementById('saveSettings')?.addEventListener('click', ()=>{
    const name = document.getElementById('setName').value.trim();
    document.getElementById('adminName').textContent = name || 'Admin';
    document.querySelector('header .avatar').textContent = (name||'A').charAt(0).toUpperCase();
    alert('✓ تم الحفظ');
  });

  // إعدادات الإشعارات (تجريبي)
  document.querySelector('form[action="update_notifications"]')?.addEventListener('submit', (e)=>{
    e.preventDefault();
    alert('✓ تم حفظ إعدادات الإشعارات (تجريبي)');
  });

  // إعدادات عامة (تجريبي)
  document.querySelector('form[action="update_settings"]')?.addEventListener('submit', (e)=>{
    e.preventDefault();
    const name = document.getElementById('setName').value.trim();
    document.getElementById('adminName').textContent = name || 'Admin';
    document.querySelector('header .avatar').textContent = (name||'A').charAt(0).toUpperCase();
    alert('✓ تم حفظ الإعدادات (تجريبي)');
  });
});

/* ========== 6) وظائف مساعدة للمستخدمين ========== */
// هذه الدوال تستخدم مع ad.php للتفاعل مع الجدول
function editUser(userId) {
  alert('تعديل المستخدم رقم: ' + userId + '\nهذه الخاصية قيد التطوير');
}

function deleteUser(userId, userName) {
  if (confirm('هل أنت متأكد من حذف المستخدم "' + userName + '"؟')) {
    alert('حذف المستخدم رقم: ' + userId + '\nهذه الخاصية قيد التطوير');
  }
}

function changeUserStatus(userId, currentStatus) {
  const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
  if (confirm(`هل تريد ${newStatus === 'active' ? 'تفعيل' : 'إيقاف'} هذا المستخدم؟`)) {
    alert(`تم ${newStatus === 'active' ? 'تفعيل' : 'إيقاف'} المستخدم رقم: ${userId}\nهذه الخاصية قيد التطوير`);
  }
}

/* ========== 7) تشغيل أوّل مرّة ========== */
(function init(){
  const lang = localStorage.getItem('langAdmin') || 'ar';
  applyI18n(lang);       // يترجم الصفحة
  setStatusUI(adminStatus);
})();

// وظيفة مساعدة لملء البيانات من التسجيل (إن وجدت)
(function fillFromSignup(){
  // 1) قراءة البيانات بأمان
  let data = {};
  try { data = JSON.parse(localStorage.getItem('signup.data') || '{}'); } catch(_) {}

  if (!data || ( !data.name && !data.email )) return; // ما في شيء

  // 2) أدوات صغيرة للحقن
  const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  const setVal  = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };

  // 3) عرض في العناصر النصية (هيدر/بطاقة... إلخ)
  setText('userName',  data.name  || '—');
  setText('userEmail', data.email || '—');

  // 4) تعبئة حقول الفورم إن وُجدت
  setVal('name',  data.name  || '');
  setVal('email', data.email || '');

  // 5) تحديث عناصر معاينة مستخدمة عندك
  setText('pv-name',   data.name  || '');
  setText('pv-email2', data.email || '');

  // 6) تحديث الاسم في الهيدر
  setText('adminName', data.name || 'Admin');
  document.querySelector('header .avatar').textContent = (data.name || 'A').charAt(0).toUpperCase();
})();