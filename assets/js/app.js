window.AAPKI=window.AAPKI||{base:'/aapkigrocery/'};
const CART_KEY='aapki_cart_v2';

/* ============================================================
   SERVICEABILITY — API-free pincode gate
   After 15s, ask the visitor for their pincode. If it is not an active
   served pincode (or none entered), disable Add-to-Cart + Login and show
   the "not serviceable" message. Browsing stays fully available.
   ============================================================ */
const AG_BASE=(window.AAPKI&&window.AAPKI.base)||'/aapkigrocery/';
const SERVED=((window.AAPKI&&window.AAPKI.servedPincodes)||[]).map(String);
const PIN_KEY='aapki_pincode_v1'; // {pincode, served}
const PIN_PROMPTED_KEY='aapki_pin_prompted_v1'; // '1' once the auto-prompt has been shown
var NOT_SERVED_MSG='We are currently working to extend our services to your region. '+
  'Please submit your inquiry via our <a href="'+AG_BASE+'contact-us/">Contact Us</a> page.';

function agPinGet(){try{return JSON.parse(localStorage.getItem(PIN_KEY)||'null');}catch(_){return null;}}
function agPinSet(o){try{localStorage.setItem(PIN_KEY,JSON.stringify(o));}catch(_){}}
function agIsServed(pin){pin=(''+(pin||'')).replace(/\D+/g,'');return pin!=='' && SERVED.indexOf(pin)!==-1;}

/* Apply served / not-served state to the whole page. */
function agApplyServiceState(served){
  document.body.classList.toggle('ag-not-served', !served);
  document.body.classList.toggle('ag-served', !!served);
  document.querySelectorAll('.add-to-cart').forEach(function(b){
    if(!served){ b.dataset.agBlocked='1'; b.setAttribute('aria-disabled','true'); b.classList.add('ag-disabled'); }
    else { delete b.dataset.agBlocked; b.removeAttribute('aria-disabled'); b.classList.remove('ag-disabled'); }
  });
  document.querySelectorAll('#accountTrigger, a[href$="login.php"], a[href$="register.php"], [data-google-login]').forEach(function(a){
    if(!served){ a.classList.add('ag-disabled'); a.setAttribute('aria-disabled','true'); }
    else { a.classList.remove('ag-disabled'); a.removeAttribute('aria-disabled'); }
  });
  var ind=document.getElementById('agPinIndicator');
  if(ind){ var p=agPinGet(); ind.textContent = (p&&p.pincode)? ('📍 '+p.pincode+(served?'':' · not serviceable')) : '📍 Set pincode'; }
}

/* Pincode prompt modal. */
function agPinPrompt(opts){
  opts=opts||{};
  if(document.getElementById('agPinModal')) return;
  var wrap=document.createElement('div'); wrap.className='ag-modal'; wrap.id='agPinModal';

  // Message-only mode: the visitor's captured geo pincode did not match any
  // active service pincode — show just the "not serviceable" message.
  if(opts.messageOnly){
    wrap.innerHTML=
      '<div class="ag-modal-card ag-pin-card">'+
        '<div class="ag-pin-head"><b>Deliver to your area</b></div>'+
        '<div class="ag-modal-body">'+
          '<p class="ag-pin-msg"><span class="ag-pin-err">'+NOT_SERVED_MSG+'</span></p>'+
          '<button type="button" class="ag-pin-skip" id="agPinClose">Close</button>'+
        '</div>'+
      '</div>';
    document.body.appendChild(wrap);
    wrap.querySelector('#agPinClose').addEventListener('click',function(){ try{wrap.remove();}catch(_){} });
    return;
  }

  wrap.innerHTML=
    '<div class="ag-modal-card ag-pin-card">'+
      '<div class="ag-pin-head"><b>Deliver to your area</b></div>'+
      '<div class="ag-modal-body">'+
        '<p>Please enter your pincode to check if we deliver to you.</p>'+
        '<form id="agPinForm" class="ag-pin-form">'+
          '<input id="agPinInput" inputmode="numeric" pattern="[1-9][0-9]{5}" maxlength="6" placeholder="6-digit pincode" required>'+
          '<button type="submit" class="btn btn-primary">Check</button>'+
        '</form>'+
        '<p class="ag-pin-msg" id="agPinMsg"></p>'+
        '<button type="button" class="ag-pin-skip" id="agPinClose">Close</button>'+
      '</div>'+
    '</div>';
  document.body.appendChild(wrap);
  var form=wrap.querySelector('#agPinForm'), input=wrap.querySelector('#agPinInput'), msg=wrap.querySelector('#agPinMsg');
  try{input.focus();}catch(_){}
  function agCloseModal(){ try{wrap.remove();}catch(_){} }
  form.addEventListener('submit',function(e){
    e.preventDefault();
    var pin=(input.value||'').replace(/\D+/g,'');
    if(!/^[1-9][0-9]{5}$/.test(pin)){ msg.innerHTML='<span class="ag-pin-err">Enter a valid 6-digit pincode.</span>'; return; }
    var served=agIsServed(pin);
    agPinSet({pincode:pin,served:served});
    if(served){
      msg.innerHTML='<span class="ag-pin-ok">✓ Great! We deliver to '+pin+'.</span>';
      agApplyServiceState(true);
    } else {
      msg.innerHTML='<span class="ag-pin-err">'+NOT_SERVED_MSG+'</span>';
      agApplyServiceState(false);
    }
    // Auto-close the popup 5 seconds after the pincode is entered.
    setTimeout(agCloseModal, 5000);
  });
  wrap.querySelector('#agPinClose').addEventListener('click',agCloseModal);
}

/* Clicking a disabled add-to-cart / login re-opens the pincode prompt. */
document.addEventListener('click',function(e){
  if(!SERVED.length) return;
  var s=agPinGet(); if(s&&s.served) return;
  var t=e.target.closest('.add-to-cart, #accountTrigger, a[href$="login.php"], a[href$="register.php"], [data-google-login]');
  if(t && (t.classList.contains('ag-disabled')||t.getAttribute('aria-disabled')==='true')){
    e.preventDefault(); e.stopPropagation(); agPinPrompt();
  }
}, true);

/* Boot the serviceability gate — prompt soon after the page loads. */
(function agServiceBoot(){
  if(!SERVED.length){ agApplyServiceState(true); return; } // not configured → don't gate
  var saved=agPinGet();
  if(saved && saved.pincode){ agApplyServiceState(!!saved.served); if(saved.served) return; }
  else { agApplyServiceState(false); }
  // Show the auto-prompt only ONCE per visitor (across all pages). If it has
  // already been shown, don't open it again automatically.
  try{ if(localStorage.getItem(PIN_PROMPTED_KEY)==='1') return; }catch(_){}
  // Ask for the pincode shortly after load (not on the auth/cart pages where it would interrupt).
  var path=location.pathname||'';
  var skip=/\/(login|register|cart)(\.php|\/)/i.test(path) || /\/auth\//i.test(path);
  if(skip) return;
  setTimeout(function(){
    var s=agPinGet();
    if(!(s&&s.served)){
      try{ localStorage.setItem(PIN_PROMPTED_KEY,'1'); }catch(_){}
      agPinPrompt();
    }
  }, 1200);
})();

const getCart=()=>{try{return JSON.parse(localStorage.getItem(CART_KEY)||'[]')}catch{return[]}};
const saveCart=c=>localStorage.setItem(CART_KEY,JSON.stringify(c));
function updateCartCount(){const n=getCart().reduce((s,i)=>s+i.qty,0);document.querySelectorAll('#cartCount').forEach(x=>x.textContent=n?`(${n})`:'');const link=document.getElementById('cartLink');if(link){link.classList.toggle('disabled-cart',n===0);link.setAttribute('aria-disabled',n===0?'true':'false');if(n===0)link.dataset.href=link.href;}}
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
  // isn't confirmed served. Prompt them for a pincode.
  if(SERVED.length){
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
   GEOLOCATION — ask the browser for the visitor's coordinates
   and POST them to api/save-location.php (saved into the PHP session).
   Requires HTTPS (or localhost); browsers block geolocation on plain HTTP.
   Trigger: click any element with id/class "agGeoBtn".
   ============================================================ */
(function agGeo(){
  var AG = window.AAPKI || {base:'/aapkigrocery/'};

  function sendPositionToPHP(position){
    var lat = position.coords.latitude;
    var lng = position.coords.longitude;
    var acc = position.coords.accuracy;
    var body = 'latitude='+encodeURIComponent(lat)+
               '&longitude='+encodeURIComponent(lng)+
               '&accuracy='+encodeURIComponent(acc)+
               '&csrf='+encodeURIComponent(AG.csrf||'');
    fetch(AG.base+'api/save-location.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':AG.csrf||''},
      body: body
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
      console.log('Location saved:', d);
      var ind = document.getElementById('agGeoStatus');
      if(ind){
        if(d.ok){
          var label = [d.area, d.city].filter(Boolean).join(', ');
          if(d.pincode) label += (label?' — ':'')+d.pincode;
          ind.textContent = '📍 ' + (label || (lat.toFixed(5)+', '+lng.toFixed(5)));
        } else {
          ind.textContent = '⚠ '+(d.message||'Failed');
        }
      }
      // If a pincode was resolved, feed it into the existing serviceability gate
      // so the visitor doesn't have to type their pincode manually.
      if(d.ok && d.pincode && typeof agPinSet==='function' && typeof agApplyServiceState==='function'){
        var served = (d.served === true) || (typeof agIsServed==='function' && agIsServed(d.pincode));
        agPinSet({pincode:d.pincode, served:served});
        agApplyServiceState(served);
        // Captured geo pincode did NOT match an active service pincode →
        // show the not-serviceable message only (no pincode input form).
        if(!served && typeof agPinPrompt==='function'){
          var existing=document.getElementById('agPinModal');
          if(existing){ try{existing.remove();}catch(_){} }
          agPinPrompt({messageOnly:true});
        }
      }
    })
    .catch(function(err){ console.warn('save-location failed:', err); });
  }

  function handleError(error){
    console.warn('Geolocation ERROR('+error.code+'): '+error.message);
    var ind = document.getElementById('agGeoStatus');
    if(ind){ ind.textContent = '⚠ '+(error.code===1?'Permission denied':'Location unavailable'); }
  }

  function requestLocation(){
    if(!('geolocation' in navigator)){
      alert('Geolocation is not supported by this browser.');
      return;
    }
    navigator.geolocation.getCurrentPosition(sendPositionToPHP, handleError, {
      enableHighAccuracy:true, timeout:10000, maximumAge:0
    });
  }

  // Wire up any trigger button on the page.
  document.addEventListener('click', function(e){
    var t = e.target.closest('#agGeoBtn, .agGeoBtn');
    if(t){ e.preventDefault(); requestLocation(); }
  });

  // Expose for manual/programmatic use.
  window.agRequestLocation = requestLocation;
})();
