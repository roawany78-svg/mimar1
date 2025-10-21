/* =======================================================================
   i18n: قاموس النصوص (عربي/إنجليزي) + أدوات تطبيق الترجمات على الصفحة
   ======================================================================= */

// كائن يحتوي النصوص لكل لغة (العربية والإنجليزية)
const i18n = {
  ar: { // القسم العربي
    codeLabel:'EN', // النص داخل زر تبديل اللغة
    toggle_title:'تبديل اللغة', // العنوان الظاهر عند تمرير الماوس على زر اللغة
    title:'إنشاء حساب', // عنوان الصفحة أو التبويب
    heading:'إنشاء حساب', // العنوان الرئيسي داخل الصفحة
    subheading:'أنشئ حسابك لبدء استخدام الخدمة', // العنوان الفرعي
    label_full_name:'الاسم الكامل', // تسمية حقل الاسم
    label_email:'البريد الإلكتروني', // تسمية حقل البريد
    label_password:'كلمة المرور', // تسمية حقل كلمة المرور
    ph_full_name:'أدخل اسمك الكامل', // نص placeholder في حقل الاسم
    ph_email:'أدخل بريدك الإلكتروني', // نص placeholder في حقل البريد
    ph_password:'أنشئ كلمة مرور', // نص placeholder في حقل كلمة المرور
    err_full_name:'الاسم الكامل مطلوب.', // رسالة خطأ لحقل الاسم
    err_email:'البريد الإلكتروني مطلوب.', // رسالة خطأ لحقل البريد
    err_password:'كلمة المرور مطلوبة.', // رسالة خطأ لحقل كلمة المرور
    type_client:'عميل (ابحث عن مقاولين)', // خيار نوع الحساب: عميل
    type_contractor:'مقاول (تقديم خدمات)', // خيار نوع الحساب: مقاول
    type_admin:'ادمن', // خيار نوع الحساب: مدير
    btn_signup:'إنشاء حساب', // نص زر إنشاء الحساب
    btn_login:'تسجيل الدخول', // نص زر تسجيل الدخول
    right_title:'نحن نبني الثقة. نحن نبني المستقبل.', // عنوان جانبي تسويقي
    right_desc:'انضم إلى آلاف العملاء والمقاولين الراضين', // وصف جانبي
    alert_terms_required:'يجب الموافقة على الشروط والأحكام', // تنبيه إذا المستخدم ما وافق على الشروط
    alert_name_invalid:'الاسم يجب أن يحتوي على حروف فقط (عربي/إنجليزي) ومسافات.', // تنبيه إذا الاسم غير صالح
    terms_html:'<label><input type="checkbox" id="agree" /> أوافق على <a href="#" target="_blank" rel="noopener">الشروط والأحكام</a></label>' // HTML لعرض خانة الموافقة
  },

  en: { // القسم الإنجليزي
    codeLabel:'AR',
    toggle_title:'Toggle language',
    title:'Create an account',
    heading:'Create an account',
    subheading:'Create your account to start using the service',
    label_full_name:'Full name',
    label_email:'Email',
    label_password:'Password',
    ph_full_name:'Enter your full name',
    ph_email:'Enter your email address',
    ph_password:'Create a password',
    err_full_name:'Full name is required.',
    err_email:'Email is required.',
    err_password:'Password is required.',
    type_client:'Client (Find Contractors)',
    type_contractor:'Contractor (providing services)',
    type_admin:'Admin',
    btn_signup:'Create an account',
    btn_login:'Log in',
    right_title:'We build trust. We build the future.',
    right_desc:'Join thousands of satisfied customers and contractors.',
    alert_terms_required:'You must agree to the terms & conditions',
    alert_name_invalid:'Name must contain letters only (Arabic/English) and spaces.',
    terms_html:'<label><input type="checkbox" id="agree" /> I agree to the <a href="#" target="_blank" rel="noopener">Terms &amp; Conditions</a></label>'
  }
};

/* =======================================================================
   أدوات ترجمة الصفحة
   ======================================================================= */

// دالة لتعيين خاصية (attribute) لأي عنصر HTML
function setAttr(el, attr, val){ 
  if(val!==undefined) el.setAttribute(attr,val); 
}

// دالة لتطبيق الترجمة على الصفحة كلها
function applyI18n(lang){
  const d = i18n[lang] || i18n.ar; // اختر القاموس حسب اللغة، أو استخدم العربي كافتراضي

  // تعيين لغة واتجاه الصفحة
  document.documentElement.lang = (lang==='en' ? 'en' : 'ar');
  document.documentElement.dir  = (lang==='en' ? 'ltr' : 'rtl');

  // تعيين عنوان التبويب
  document.title = d.title;

  // تعديل نص زر اللغة
  document.getElementById('langLabel').textContent = d.codeLabel;

  // ترجمة العناصر التي تحتوي على data-i18n
  document.querySelectorAll('[data-i18n]').forEach(el=>{
    const k = el.getAttribute('data-i18n');
    if(d[k]!==undefined) el.textContent = d[k];
  });

  // ترجمة خصائص العناصر مثل placeholder أو aria-label
  document.querySelectorAll('[data-i18n-attr]').forEach(el=>{
    el.getAttribute('data-i18n-attr').split(',').forEach(pair=>{
      const [attr, key] = pair.split(':').map(s=>s.trim());
      if(attr && key && d[key]!==undefined) setAttr(el, attr, d[key]);
    });
  });

  // استبدال محتوى قسم الشروط بالنسخة الصحيحة للغة
  const terms = document.getElementById('termsBlock');
  if(terms){ terms.innerHTML = d.terms_html; }

  // حفظ اللغة المختارة في localStorage
  localStorage.setItem('lang', lang);

  // تعريف متغير عام للوصول للغة من أي مكان
  window.__APP_LANG__ = lang;
}

// دالة ترجمة سريعة لأي مفتاح نصي
function t(key){
  const lang = window.__APP_LANG__ || localStorage.getItem('lang') || 'ar';
  const d = i18n[lang] || i18n.ar;
  return d[key] || key;
}

/* =======================================================================
   أدوات النموذج والتحقق + التحويل حسب نوع الحساب
   ======================================================================= */

// اختصار لاختيار عنصر من الصفحة
const $ = (sel)=>document.querySelector(sel);

// دالة لإرجاع نوع الحساب المحدد (عميل، مقاول، ادمن)
function getSelectedAccountType(){
  const s = document.querySelector('input[name="account-type"]:checked');
  return s ? s.value : 'client'; // إذا ما فيه اختيار، خذ "client" كافتراضي
}

// دالة لإظهار أو إخفاء رسالة خطأ
function setError(id, show){
  const el = document.getElementById(id);
  if(!el) return;
  el.hidden = !show;
}

// أنماط regex لتحديد المحارف المسموح بها في الاسم
const NAME_CHAR_REGEX   = /^[A-Za-z\u0600-\u06FF\s]+$/; // حروف عربي/إنجليزي ومسافة فقط
const NAME_CHAR_SINGLE  = /[A-Za-z\u0600-\u06FF\s]/; // حرف واحد فقط
const DIGIT_REGEX       = /[0-9\u0660-\u0669\u06F0-\u06F9]/; // الأرقام باللغات المختلفة

// دالة لتنظيف الاسم من الرموز غير المسموحة أو المسافات المكررة
function sanitizeName(value){
  let out = '';
  for (const ch of value) if (NAME_CHAR_SINGLE.test(ch)) out += ch; // أضف فقط الحروف المسموحة
  return out.replace(/\s{2,}/g,' ').trimStart(); // إزالة المسافات المكررة
}

// دالة للتحقق من أن الاسم صالح وغير فارغ
function isValidName(value){
  const s = value.trim();
  return s.length > 0 && NAME_CHAR_REGEX.test(s);
}

// دالة التحقق الكامل للنموذج
function validateForm(){
  // إخفاء كل الأخطاء قبل التحقق
  setError('full-name-error', false);
  setError('email-error', false);
  setError('password-error', false);

  // الحصول على القيم من الحقول
  const nameEl = $('#full-name');
  const email  = $('#email').value.trim();
  const pass   = $('#password').value.trim();
  const agree  = document.querySelector('#termsBlock #agree');

  let ok = true; // متغير لتحديد صلاحية النموذج

  // التحقق من الاسم
  if(nameEl.value.trim()===''){ setError('full-name-error', true); ok=false; }
  if(!isValidName(nameEl.value)){ alert(t('alert_name_invalid')); nameEl.focus(); ok = false; }

  // التحقق من البريد وكلمة المرور
  if(email===''){ setError('email-error', true); ok=false; }
  if(pass===''){ setError('password-error', true); ok=false; }

  // التحقق من الموافقة على الشروط
  if(!agree || !agree.checked){
    alert(t('alert_terms_required'));
    if(agree){ 
      agree.focus(); 
      agree.scrollIntoView({behavior:'smooth', block:'center'}); 
    }
    ok = false;
  }

  return ok; // إرجاع النتيجة (صالح أو لا)
}

// تخزين بيانات التسجيل محليًا في المتصفح
function persistSignupData({name, email, type}){
  const payload = {
    name, // الاسم
    email, // البريد
    type, // نوع الحساب
    ts: Date.now(), // الطابع الزمني (وقت التسجيل)
    provider:'form' // مزود الدخول (بالفورم العادي فقط)
  };
  localStorage.setItem('signup.data', JSON.stringify(payload)); // تخزين البيانات كـ JSON
}

// التحويل للصفحة المناسبة حسب نوع الحساب
function redirectByType(type){
  const map = { client:'cei.html', contractor:'pro.html', admin:'ad.html' }; // خريطة التوجيه
  const target = map[type] || 'cei.html'; // الصفحة الافتراضية لو غير معروفة
  location.assign(target); // تحويل المستخدم
}

// دالة لحفظ البيانات ثم تحويل المستخدم مباشرة
async function persistAndRedirect(type){
  const name = $('#full-name').value.trim();
  const email = $('#email').value.trim();
  persistSignupData({ name, email, type }); // حفظ البيانات
  redirectByType(type); // تحويل المستخدم
}

/* =======================================================================
   تشغيل الصفحة عند التحميل
   ======================================================================= */

document.addEventListener('DOMContentLoaded', ()=>{ // عند تحميل الصفحة

  const initialLang = localStorage.getItem('lang') || 'ar'; // تحديد اللغة الأولى
  applyI18n(initialLang); // تطبيق الترجمة

  // إعداد زر تبديل اللغة
  document.getElementById('langToggle')?.addEventListener('click', ()=>{
    const current = localStorage.getItem('lang') || 'ar';
    applyI18n(current==='ar' ? 'en' : 'ar'); // تبديل بين العربي والإنجليزي
  });

  // التحكم في كتابة الاسم (منع الأرقام والرموز)
  const nameEl = document.getElementById('full-name');

  // عند ضغط المستخدم على لوحة المفاتيح
  nameEl?.addEventListener('keydown', (e)=>{
    const ctrlKeys = ['Backspace','Delete','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home','End','Tab','Enter'];
    if (e.ctrlKey || e.metaKey || e.altKey || ctrlKeys.includes(e.key)) return; // السماح بمفاتيح التحكم
    if (DIGIT_REGEX.test(e.key)) { e.preventDefault(); return; } // منع الأرقام
    if (!NAME_CHAR_SINGLE.test(e.key)) { e.preventDefault(); } // منع الرموز
  });

  // عند الكتابة داخل الحقل (تنظيف فوري)
  nameEl?.addEventListener('input', ()=>{
    const clean = sanitizeName(nameEl.value);
    if(nameEl.value !== clean) nameEl.value = clean;
  });

  // عند لصق نص داخل الحقل (يتم تنظيفه قبل اللصق)
  nameEl?.addEventListener('paste', (e)=>{
    const text = (e.clipboardData || window.clipboardData).getData('text');
    const clean = sanitizeName(text);
    if(clean !== text){ 
      e.preventDefault(); 
      document.execCommand('insertText', false, clean); 
    }
  });

  // عند إرسال النموذج (الزر "إنشاء حساب")
  const form = document.getElementById('signupForm');
  form?.addEventListener('submit', (e)=>{
    e.preventDefault(); // منع الإرسال الافتراضي
    if(!validateForm()) return; // تحقق أولًا
    const type = getSelectedAccountType(); // نوع الحساب
    persistAndRedirect(type); // حفظ وتحويل
  });
});