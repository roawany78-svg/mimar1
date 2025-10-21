// بيانات تجريبية للطلبات
let items = [
  {
    name: "طلب تصميم هندسي",
    desc: "خدمة إعداد مخطط منزل حديث",
    price: 350,
    img: "https://i.imgur.com/bzGzRkY.jpg"
  },
  {
    name: "طلب بناء وتشطيب",
    desc: "تشطيب واجهة منزل من الحجر الطبيعي",
    price: 1200,
    img: "https://i.imgur.com/YZj1uJ1.jpg"
  },
  {
    name: "استشارة هندسية",
    desc: "مقابلة مهندس لمراجعة المخطط",
    price: 150,
    img: "https://i.imgur.com/nEp7X6h.jpg"
  }
];

const cartContainer = document.getElementById("cart-items");
const totalText = document.getElementById("total");

// عرض الطلبات
function renderCart() {
  cartContainer.innerHTML = "";
  let total = 0;

  items.forEach((item, index) => {
    total += item.price;

    const div = document.createElement("div");
    div.classList.add("cart-item");
    div.innerHTML = `
      <img src="${item.img}" alt="${item.name}">
      <div class="item-details">
        <h3>${item.name}</h3>
        <p>${item.desc}</p>
        <span class="item-price">${item.price} ر.س</span>
      </div>
      <button class="remove-btn" onclick="removeItem(${index})">حذف</button>
    `;
    cartContainer.appendChild(div);
  });

  totalText.innerHTML = $;{total} ر.س;
}

// حذف الطلب
window.removeItem = function(index) {
  items.splice(index, 1);
  renderCart();
}

// إضافة طلب جديد تجريبي
window.addItem = function() {
  const newItem = {
    name: "طلب جديد",
    desc: "خدمة إضافية من معمار",
    price: Math.floor(Math.random() * 500) + 100,
    img: "https://i.imgur.com/0Z8FqkC.jpg"
  };
  items.push(newItem);
  renderCart();
}

// تأكيد الطلب
window.confirmOrder = function() {
  alert("تم تأكيد الطلب بنجاح ✅");
  items = [];
  renderCart();
}

// تشغيل أولي بعد تحميل DOM
document.addEventListener("DOMContentLoaded", renderCart);
