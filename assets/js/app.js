window.AAPKI=window.AAPKI||{base:'/aapkigrocery/'};
const CART_KEY='aapki_cart_v2';

/* ============================================================
   SERVICEABILITY — API-free pincode gate
   On a guest's FIRST visit we ask for their pincode right away. If it is an
   active served pincode we show Add-to-Cart; otherwise we hide Add-to-Cart
   and show a friendly "we'll be there soon" message. The Login button is
   ALWAYS visible. Logged-in members are never asked (they already ordered).
   The pincode is remembered (cookie + localStorage) so returning guests are
   not asked again. Browsing stays fully available in every case.
   ============================================================ */
const AG_BASE=(window.AAPKI&&window.AAPKI.base)||'/aapkigrocery/';
const SERVED=((window.AAPKI&&window.AAPKI.servedPincodes)||[]).map(String);
const IS_MEMBER=!!(window.AAPKI&&window.AAPKI.isMember); // logged-in user → skip gate
const PIN_KEY='aapki_pincode_v1'; // {pincode, served}
const PIN_COOKIE='aapki_pin'; // captured pincode persisted across visits (180d)
var NOT_SERVED_MSG='We are currently working to extend our services to your region. '+
  'Please submit your inquiry via our <a href="'+AG_BASE+'contact-us/">Contact Us</a> page.';

/* Cookie helpers (persist across revisits, incl. new sessions). */
function agSetCookie(name,val,days){var d=new Date();d.setTime(d.getTime()+(days*864e5));document.cookie=name+'='+encodeURIComponent(val)+';expires='+d.toUTCString()+';path=/;SameSite=Lax';}
function agGetCookie(name){var m=document.cookie.match('(?:^|; )'+name.replace(/([.$?*|{}()\[\]\\\/\+^])/g,'\\$1')+'=([^;]*)');return m?decodeURIComponent(m[1]):null;}

function agPinGet(){try{return JSON.parse(localStorage.getItem(PIN_KEY)||'null');}catch(_){return null;}}
function agPinSet(o){try{localStorage.setItem(PIN_KEY,JSON.stringify(o));}catch(_){} if(o&&o.pincode){agSetCookie(PIN_COOKIE,o.pincode,180);}}
function agIsServed(pin){pin=(''+(pin||'')).replace(/\D+/g,'');return pin!=='' && SERVED.indexOf(pin)!==-1;}

/* Apply served / not-served state to the whole page.
   Only Add-to-Cart is gated — Login / Register / account always stay active. */
function agApplyServiceState(served){
  document.body.classList.toggle('ag-not-served', !served);
  document.body.classList.toggle('ag-served', !!served);
  document.querySelectorAll('.add-to-cart').forEach(function(b){
    if(!served){ b.dataset.agBlocked='1'; b.setAttribute('aria-disabled','true'); b.classList.add('ag-disabled'); }
    else { delete b.dataset.agBlocked; b.removeAttribute('aria-disabled'); b.classList.remove('ag-disabled'); }
  });
}

/* Pincode prompt modal. */
function agPinPrompt(opts){
  opts=opts||{};
  if(document.getElementById('agPinModal')) return;
  var wrap=document.createElement('div'); wrap.className='ag-modal'; wrap.id='agPinModal';

  // MANDATORY gate: no close (✕) button and no "Maybe later" link. A guest
  // must enter a pincode we actually serve before they can continue. The modal
  // closes ONLY after a valid, served pincode is entered.
  wrap.innerHTML=
    '<div class="ag-modal-card ag-pin-card">'+
      '<div class="ag-pin-head"><b>Check delivery in your area</b></div>'+
      '<div class="ag-modal-body">'+
        '<p>Please enter your 6-digit pincode to check if we deliver to you.</p>'+
        '<form id="agPinForm" class="ag-pin-form">'+
          '<input id="agPinInput" inputmode="numeric" pattern="[1-9][0-9]{5}" maxlength="6" placeholder="6-digit pincode" required>'+
          '<button type="submit" class="btn btn-primary">Check</button>'+
        '</form>'+
        '<p class="ag-pin-msg" id="agPinMsg"></p>'+
      '</div>'+
    '</div>';
  document.body.appendChild(wrap);
  var form=wrap.querySelector('#agPinForm'), input=wrap.querySelector('#agPinInput'), msg=wrap.querySelector('#agPinMsg');
  try{input.focus();}catch(_){}
  var acTimer=null;
  function agCloseModal(){ try{clearTimeout(acTimer);wrap.remove();}catch(_){} }
  form.addEventListener('submit',function(e){
    e.preventDefault();
    var pin=(input.value||'').replace(/\D+/g,'');
    if(!/^[1-9][0-9]{5}$/.test(pin)){ msg.innerHTML='<span class="ag-pin-err">Enter a valid 6-digit pincode.</span>'; return; }
    var served=agIsServed(pin);
    agPinSet({pincode:pin,served:served}); // remembers pincode (cookie 180d + localStorage)
    if(served){
      msg.innerHTML='<span class="ag-pin-ok">\u2713 Great! We deliver to '+pin+'. You can start shopping.</span>';
      agApplyServiceState(true);
      acTimer=setTimeout(agCloseModal, 1500); // close shortly after success
    } else {
      // Not served: keep the modal OPEN so they can try another pincode.
      msg.innerHTML='<span class="ag-pin-err">'+NOT_SERVED_MSG+'</span>';
      agApplyServiceState(false);
      try{input.value='';input.focus();}catch(_){}
    }
  });
}

/* Clicking a disabled Add-to-Cart re-opens the pincode prompt. */
document.addEventListener('click',function(e){
  if(!SERVED.length || IS_MEMBER) return;
  var s=agPinGet(); if(s&&s.served) return;
  var t=e.target.closest('.add-to-cart');
  if(t && (t.classList.contains('ag-disabled')||t.getAttribute('aria-disabled')==='true')){
    e.preventDefault(); e.stopPropagation(); agPinPrompt();
  }
}, true);

/* Boot the serviceability gate. */
(function agServiceBoot(){
  if(!SERVED.length){ agApplyServiceState(true); return; } // not configured → don't gate

  // Logged-in members are trusted (they've ordered / have a delivery address):
  // never ask them for a pincode and keep everything enabled.
  if(IS_MEMBER){ agApplyServiceState(true); return; }

  // 1) Returning guest: if we already captured a pincode, apply it silently.
  var cookiePin = agGetCookie(PIN_COOKIE);
  if(cookiePin){
    var served = agIsServed(cookiePin);
    agPinSet({pincode:cookiePin, served:served});
    agApplyServiceState(served);
    return; // remembered → never nag the returning guest again
  }

  // Default state until the guest tells us their pincode: not served (cart gated).
  agApplyServiceState(false);

  // Don't interrupt the flow on auth / cart pages.
  var path=location.pathname||'';
  if(/\/(login|register|cart)(\.php|\/)/i.test(path) || /\/auth\//i.test(path)) return;

  // 2) First-time guest: ask for the pincode right away so they immediately
  // know whether we deliver to them (no 15s wait, no silent gating surprise).
  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded', agPinPrompt);
  } else {
    agPinPrompt();
  }
})();

const getCart=()=>{try{return JSON.parse(localStorage.getItem(CART_KEY)||'[]')}catch{return[]}};
const saveCart=c=>localStorage.setItem(CART_KEY,JSON.stringify(c));
function updateCartCount(){const n=getCart().reduce((s,i)=>s+i.qty,0);document.querySelectorAll('#cartCount').forEach(x=>x.textContent=n?`(${n})`:'');const link=document.getElementById('cartLink');if(link){link.classList.toggle('disabled-cart',n===0);link.setAttribute('aria-disabled',n===0?'true':'false');if(n===0)link.dataset.href=link.href;}agUpdateFloatingCart(n);}

/* ============================================================
   FLOATING CART WIDGET ("cart info ticket") — appears once the
   cart has at least 1 item. Shows item count + live ₹ total,
   like other grocery sites (Blinkit / BigBasket / Zepto).
   Desktop: compact ticket card, fixed bottom-right.
   Mobile:  full-width strip, fixed at the bottom.
   Stays fixed while scrolling & across page loads; hidden on the
   cart page itself.
   ============================================================ */
var agFloatingCartEl=null, agCartTotal=null, agTotalTimer=null;
function agOnCartPage(){ return /\/cart(\.php|\/)?($|[?#])/i.test(location.pathname+location.search) || !!document.getElementById('cartItems'); }
function agFloatingCartAllowed(){ return window.matchMedia('(max-width:768px)').matches; } // mobile only
function agBuildFloatingCart(){
  if(agFloatingCartEl || agOnCartPage() || !agFloatingCartAllowed()) return;
  var base=(window.AAPKI&&window.AAPKI.base)||'/aapkigrocery/';
  var a=document.createElement('a');
  a.id='agFloatingCart';
  a.className='ag-floating-cart';
  a.href=base+'cart/';
  a.setAttribute('aria-label','View cart');
  a.innerHTML=
    '<span class="agfc-icon" aria-hidden="true">🛒<b class="agfc-badge" id="agfcBadge">0</b></span>'+
    '<span class="agfc-info">'+
      '<span class="agfc-count" id="agfcCount">0 items</span>'+
      '<span class="agfc-total" id="agfcTotal"></span>'+
    '</span>'+
    '<span class="agfc-cta">View cart <span aria-hidden="true">→</span></span>';
  document.body.appendChild(a);
  agFloatingCartEl=a;
}
/* Fetch the live ₹ total for the current cart (debounced). */
function agRefreshCartTotal(){
  if(agOnCartPage()) return;
  clearTimeout(agTotalTimer);
  agTotalTimer=setTimeout(function(){
    var items=getCart(); if(!items.length){ agCartTotal=null; return; }
    var base=(window.AAPKI&&window.AAPKI.base)||'/aapkigrocery/';
    fetch(base+'api/cart-products.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({items:items})})
      .then(function(r){return r.json();})
      .then(function(d){
        if(!d||!d.ok||!d.items){ return; }
        var t=0; d.items.forEach(function(x){ t+=Number(x.line_total||0); });
        agCartTotal=t;
        var el=document.getElementById('agfcTotal');
        if(el) el.textContent='₹'+t.toFixed(2);
      }).catch(function(){});
  },250);
}
function agUpdateFloatingCart(n){
  if(agOnCartPage() || !agFloatingCartAllowed()) return; // mobile only, not on cart page
  if(n>0 && !agFloatingCartEl) agBuildFloatingCart();
  if(!agFloatingCartEl) return;
  var show=n>0;
  agFloatingCartEl.classList.toggle('show', show);
  document.body.classList.toggle('ag-has-floating-cart', show);
  if(show){
    var badge=document.getElementById('agfcBadge'); if(badge) badge.textContent=n;
    var cnt=document.getElementById('agfcCount'); if(cnt) cnt.textContent=n+(n===1?' item':' items');
    var tot=document.getElementById('agfcTotal');
    if(tot){ tot.textContent = (agCartTotal!=null) ? '₹'+Number(agCartTotal).toFixed(2) : ''; }
    agRefreshCartTotal(); // keep the ₹ total fresh
  } else {
    agCartTotal=null;
  }
}
updateCartCount();
// Product variants
for(const sel of document.querySelectorAll('.variant-select')) sel.addEventListener('change',()=>{const o=sel.selectedOptions[0],card=sel.closest('.product-card');card.querySelector('.variant-price').textContent='₹'+Number(o.dataset.price).toFixed(2);card.querySelector('.variant-mrp').textContent=Number(o.dataset.mrp)>Number(o.dataset.price)?'₹'+Number(o.dataset.mrp).toFixed(2):''});
for(const btn of document.querySelectorAll('.variant-pill')) btn.addEventListener('click',()=>{const wrap=btn.closest('.detail-info');wrap.querySelectorAll('.variant-pill').forEach(x=>x.classList.remove('active'));btn.classList.add('active');wrap.querySelector('#detailPrice').textContent='₹'+Number(btn.dataset.price).toFixed(2);wrap.querySelector('#detailMrp').textContent=Number(btn.dataset.mrp)>Number(btn.dataset.price)?'₹'+Number(btn.dataset.mrp).toFixed(2):''});

// Live cart. A product shows Coming Soon only when the selected variant has zero stock.
const cartButtons=[...document.querySelectorAll('.add-to-cart')];
function cartRead(){try{return JSON.parse(localStorage.getItem(CART_KEY)||'[]')}catch{return[]}}
function cartWrite(c){localStorage.setItem(CART_KEY,JSON.stringify(c));updateCartCount()}
function syncButton(btn){const card=btn.closest('.product-card');const sel=card?.querySelector('.variant-select');const opt=sel?.selectedOptions?.[0];if(!opt)return;const stock=Number(opt.dataset.stock||0);btn.dataset.variant=opt.value;btn.disabled=stock<=0;btn.textContent=stock>0?'Add to Cart':'Coming Soon'}
function agDoAdd(btn){const card=btn.closest('.product-card');const sel=card.querySelector('.variant-select');const variant=sel.selectedOptions[0];const stock=Number(variant.dataset.stock||0);const c=cartRead();const id=Number(variant.value);const found=c.find(x=>x.variant_id===id);if(found)found.qty=Math.min(found.qty+1,stock);else c.push({variant_id:id,qty:1});cartWrite(c);btn.textContent='Added ✓';setTimeout(()=>syncButton(btn),900);}
for(const btn of cartButtons){syncButton(btn);btn.addEventListener('click',()=>{
  if(btn.disabled)return;
  // Serviceability gate: block if a served-pincode list exists and the visitor
  // isn't confirmed served. Prompt them for a pincode. Members bypass the gate.
  if(SERVED.length && !IS_MEMBER){
    var s=agPinGet();
    if(!(s&&s.served)){ agPinPrompt(); return; }
  }
  agDoAdd(btn);
})}
for(const sel of document.querySelectorAll('.variant-select')) sel.addEventListener('change',()=>{syncButton(sel.closest('.product-card').querySelector('.add-to-cart'))});
const cartRoot=document.getElementById('cartItems');
if(cartRoot){(async()=>{const items=cartRead();if(!items.length){cartRoot.innerHTML='<div class="empty"><h2>Your basket is empty</h2><p>Add fresh products to get started.</p><a class="btn btn-primary" href="'+(window.AAPKI?.base||'/aapkigrocery/')+'">Start shopping</a></div>';const back=(window.AAPKI?.base||'/aapkigrocery/');setTimeout(()=>{window.location.replace(back);},1200);return}const r=await fetch((window.AAPKI?.base||'/aapkigrocery/')+'api/cart-products.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({items})});const d=await r.json();if(!d.ok){cartRoot.innerHTML='<p class="alert">Could not load cart.</p>';return}localStorage.setItem(CART_KEY,JSON.stringify(d.items.map(x=>({variant_id:Number(x.variant_id),qty:Number(x.qty)}))));renderCart(d.items)})()}
function renderCart(items){if(!cartRoot)return;let subtotal=0;cartRoot.innerHTML=items.map(i=>{subtotal+=Number(i.line_total);return `<div class="cart-item" data-variant="${i.variant_id}"><img src="${i.image}" alt="${i.name}"><div><h3>${i.name}</h3><small>${i.label}</small><div class="qty-controls"><button type="button" data-dec="${i.variant_id}">−</button><b>${i.qty}</b><button type="button" data-inc="${i.variant_id}">+</button><button type="button" class="remove-cart" aria-label="Remove item" title="Remove" data-remove="${i.variant_id}">×</button></div></div><strong>₹${Number(i.line_total).toFixed(2)}</strong></div>`}).join('');const handling=Number(window.AAPKI_HANDLING||0);const delivery=subtotal>=499?0:40;const total=subtotal+handling+delivery;document.getElementById('cartSubtotal').textContent='₹'+subtotal.toFixed(2);const h=document.getElementById('cartHandling');if(h)h.textContent='₹'+handling.toFixed(2);const d=document.getElementById('cartDelivery');if(d)d.textContent='₹'+delivery.toFixed(2);document.getElementById('cartTotal').textContent='₹'+total.toFixed(2);cartRoot.querySelectorAll('[data-inc],[data-dec],[data-remove]').forEach(b=>b.addEventListener('click',async()=>{const id=Number(b.dataset.inc||b.dataset.dec||b.dataset.remove);let c=cartRead();const x=c.find(v=>v.variant_id===id);if(!x)return;if(b.dataset.inc)x.qty++;if(b.dataset.dec)x.qty--;if(b.dataset.remove)x.qty=0;c=c.filter(v=>v.qty>0);cartWrite(c);updateCartCount();const r=await fetch((window.AAPKI?.base||'/aapkigrocery/')+'api/cart-products.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({items:c})});const d=await r.json();renderCart(d.items||[])}))}
// Search suggestions
const search=document.getElementById('searchBox'),suggest=document.getElementById('searchSuggest');let timer;
if(search){search.addEventListener('input',()=>{clearTimeout(timer);const q=search.value.trim();if(q.length<2){suggest.innerHTML='';suggest.classList.remove('show');return;}timer=setTimeout(async()=>{try{const r=await fetch((window.AAPKI?.base||'/aapkigrocery/')+'api/search.php?q='+encodeURIComponent(q));const d=await r.json();suggest.innerHTML=d.items.map(x=>`<a href="${(window.AAPKI?.base||'/aapkigrocery/')+(x.category_slug?x.category_slug+'/':'product/')+encodeURIComponent(x.slug)+'/'}"><img src="${x.image||((window.AAPKI?.base||'/aapkigrocery/')+'assets/images/default-banner.svg')}" alt=""><span>${x.name}<small>₹${Number(x.price).toFixed(2)}</small></span></a>`).join('')||'<div class="suggest-empty">No matching product</div>';suggest.classList.add('show')}catch(e){}},180)});document.addEventListener('click',e=>{if(!e.target.closest('.search'))suggest?.classList.remove('show')})}
// Infinite scroll
const load=document.getElementById('loadMore');let loading=false;if(load){const io=new IntersectionObserver(async entries=>{if(!entries[0].isIntersecting||loading)return;loading=true;const page=Number(load.dataset.page);const params=new URLSearchParams({page,cat:load.dataset.cat||'',q:load.dataset.q||''});try{const r=await fetch((window.AAPKI?.base||'/aapkigrocery/')+'api/load-products.php?'+params);const d=await r.json();document.getElementById('productGrid').insertAdjacentHTML('beforeend',d.html);load.dataset.page=d.next;if(d.count<24)load.remove()}finally{loading=false}}, {rootMargin:'600px'});io.observe(load)}
const sortSelect=document.getElementById('sortSelect');if(sortSelect){sortSelect.addEventListener('change',()=>{const u=new URL(location.href);u.searchParams.set('sort',sortSelect.value);u.searchParams.delete('page');location.href=u.toString()})}

const accountTrigger=document.getElementById('accountTrigger'),accountDropdown=document.getElementById('accountDropdown');accountTrigger?.addEventListener('click',()=>accountDropdown?.classList.toggle('show'));document.addEventListener('click',e=>{if(!e.target.closest('.account-menu'))accountDropdown?.classList.remove('show')});document.getElementById('cartLink')?.addEventListener('click',e=>{if(e.currentTarget.classList.contains('disabled-cart'))e.preventDefault();});


// ── Hero Banner Slider — handled inline in index.php ─────────

/* ============================================================
   PWA "ADD TO HOME SCREEN" PROMPT  (MOBILE ONLY)
   Shown to a logged-in user who has placed >=1 order (canInstall).
   - If the app is already installed / running standalone -> never ask.
   - If the user installs via our prompt -> remember it -> never ask again.
   - Otherwise (dismissed) -> ask again, once per browsing session.
   ============================================================ */
(function agInstallPromptInit(){
  var AG = window.AAPKI || {};
  if(!AG.canInstall) return;                              // only eligible users

  // Mobile only.
  var isMobile = window.matchMedia('(max-width:768px)').matches
              || /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
  if(!isMobile) return;

  var INSTALLED_KEY='aapki_pwa_installed_v1';             // persistent (localStorage)
  var SESSION_KEY='aapki_pwa_asked_session_v1';           // once-per-session (sessionStorage)

  // Already launched from the home-screen icon? Then it's installed.
  var standalone = window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;
  if(standalone){ try{localStorage.setItem(INSTALLED_KEY,'1');}catch(_){ } return; }

  // Previously installed via our prompt? Never ask again.
  try{ if(localStorage.getItem(INSTALLED_KEY)==='1') return; }catch(_){}

  // Already asked this session? Honour "once per visit".
  try{ if(sessionStorage.getItem(SESSION_KEY)==='1') return; }catch(_){}

  var deferredPrompt=null;   // Android: the captured beforeinstallprompt event
  var isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent) && !window.MSStream;

  function markAskedThisSession(){ try{sessionStorage.setItem(SESSION_KEY,'1');}catch(_){ } }
  function closeBanner(){ var b=document.getElementById('agInstallBanner'); if(b) b.remove(); }

  function showBanner(mode){   // mode: 'android' | 'ios'
    if(document.getElementById('agInstallBanner')) return;
    markAskedThisSession();
    var base=AG.base||'/aapkigrocery/';
    var wrap=document.createElement('div');
    wrap.id='agInstallBanner';
    wrap.className='ag-install-banner';
    var actionHtml = (mode==='android')
      ? '<button type="button" class="agib-install" id="agibInstall">Install</button>'
      : '<span class="agib-ios">Tap <b>Share</b> <span aria-hidden="true">⬆️</span> then <b>Add to Home Screen</b></span>';
    wrap.innerHTML=
      '<img class="agib-icon" src="'+base+'assets/favicon-192.png" alt="">'+
      '<div class="agib-text"><b>Add Aapki Grocery to your home screen</b>'+
        '<small>One-tap access, like an app.</small></div>'+
      '<div class="agib-actions">'+actionHtml+
        '<button type="button" class="agib-close" id="agibClose" aria-label="Dismiss">&times;</button>'+
      '</div>';
    document.body.appendChild(wrap);
    requestAnimationFrame(function(){ wrap.classList.add('show'); });

    var closeBtn=document.getElementById('agibClose');
    if(closeBtn) closeBtn.addEventListener('click', closeBanner);

    if(mode==='android'){
      var btn=document.getElementById('agibInstall');
      if(btn) btn.addEventListener('click', function(){
        if(!deferredPrompt){ closeBanner(); return; }
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function(choice){
          if(choice && choice.outcome==='accepted'){
            try{localStorage.setItem(INSTALLED_KEY,'1');}catch(_){}
          }
          deferredPrompt=null;
          closeBanner();
        });
      });
    }
  }

  // Android / Chromium: capture the install event and show our banner.
  window.addEventListener('beforeinstallprompt', function(e){
    e.preventDefault();
    deferredPrompt=e;
    showBanner('android');
  });

  // If the browser fires appinstalled (any path), remember it permanently.
  window.addEventListener('appinstalled', function(){
    try{localStorage.setItem(INSTALLED_KEY,'1');}catch(_){}
    closeBanner();
  });

  // iOS Safari has no beforeinstallprompt — show manual instructions once.
  if(isIOS){
    setTimeout(function(){ showBanner('ios'); }, 1200);
  }
})();
