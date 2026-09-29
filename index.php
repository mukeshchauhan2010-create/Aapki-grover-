<?php require_once __DIR__.'/config/db.php'; $pdo=db(); $q=trim($_GET['q']??'');$cat=trim($_GET['cat']??'');$sort=$_GET['sort']??'featured';$page=max(1,(int)($_GET['page']??1));$limit=24;$offset=($page-1)*$limit;

// ── Category lookup — match slug against both parent and child cats ──
$catRow=null;
if($cat){
    $s=$pdo->prepare('SELECT c.*,p.name parent_name,p.slug parent_slug FROM categories c LEFT JOIN categories p ON p.id=c.parent_id WHERE c.slug=? AND c.is_active=1 LIMIT 1');
    $s->execute([$cat]);$catRow=$s->fetch();
    if($catRow){$title=$catRow['meta_title']?:$catRow['name'].' | '.APP_NAME;$description=$catRow['meta_description']?:'Shop '.$catRow['name'].' at Aapki Grocery.';$keywords=$catRow['meta_keywords']?:$catRow['name'];}
}
if(!$catRow && $q){$title='Search: '.$q.' | '.APP_NAME;$description='Search results for '.$q.' at Aapki Grocery.';$keywords=$q.', grocery, fresh food';}

// ── Product query — if viewing a parent cat, include its subcategories too ──
$where=' WHERE p.is_active=1';$params=[];
if($catRow){
    // If parent: include all children; if child: just itself
    $childIds=$pdo->prepare('SELECT id FROM categories WHERE parent_id=? AND is_active=1');
    $childIds->execute([$catRow['id']]);$cids=array_column($childIds->fetchAll(),'id');
    if($cids){
        $placeholders=implode(',',array_fill(0,count($cids)+1,'?'));
        $where.=' AND p.category_id IN('.$placeholders.')';
        $params=array_merge([$catRow['id']],$cids);
    } else {
        $where.=' AND p.category_id=?';$params[]=$catRow['id'];
    }
}
if($q){$where.=' AND (p.name LIKE ? OR p.description LIKE ? OR p.search_terms LIKE ? OR EXISTS(SELECT 1 FROM product_synonyms ps WHERE ps.product_id=p.id AND ps.term LIKE ?))';$like='%'.$q.'%';$params=array_merge($params,[$like,$like,$like,$like]);}

$count=$pdo->prepare('SELECT COUNT(*) FROM products p'.$where);$count->execute($params);$total=(int)$count->fetchColumn();
$order='p.is_featured DESC,p.id DESC';if($sort==='price_low')$order='p.price ASC';elseif($sort==='price_high')$order='p.price DESC';elseif($sort==='name')$order='p.name ASC';elseif($sort==='new')$order='p.id DESC';
$sql='SELECT p.*,c.name category_name,c.slug category_slug FROM products p JOIN categories c ON c.id=p.category_id'.$where.' ORDER BY '.$order.' LIMIT '.$limit.' OFFSET '.$offset;
$st=$pdo->prepare($sql);$st->execute($params);$products=$st->fetchAll();

// ── Category nav — top-level only for main nav strip ──
$cats=$pdo->query('SELECT * FROM categories WHERE is_active=1 AND (parent_id IS NULL OR parent_id=0) ORDER BY sort_order,id')->fetchAll();

// ── Sub-categories under each top-level cat ──
$subCats=[];
foreach($pdo->query('SELECT * FROM categories WHERE is_active=1 AND parent_id IS NOT NULL ORDER BY sort_order,id') as $sc)
    $subCats[$sc['parent_id']][]=$sc;
$banners=$pdo->query("SELECT * FROM banners WHERE is_active=1 ORDER BY sort_order,id ASC")->fetchAll();if(!$banners)$banners=[['id'=>0,'title'=>'','subtitle'=>'','image'=>'assets/images/banners/home-farm-to-home.webp','link'=>'']];$preloadImage=$banners[0]['image']??'assets/images/banners/home-farm-to-home.webp';if(!preg_match('~^https?://~i',$preloadImage))$preloadImage=url(ltrim($preloadImage,'/'));$popup=setting_bool($pdo,'store_popup_enabled');
include __DIR__.'/includes/header.php';
?>
<?php if($page===1 && !$q && !$catRow): ?>
<section class="hero" aria-label="Featured banners">
  <div class="hero-slider" id="heroSlider" data-autoplay="5000">

    <!-- Slides track -->
    <div class="hero-track" id="heroTrack">
      <?php foreach($banners as $i=>$b):
        $imgSrc = preg_match('~^https?://~i',$b['image']) ? $b['image'] : url(ltrim($b['image'],'/'));
        $fallback = url('assets/images/banners/home-farm-to-home.webp');
      ?>
      <div class="hero-slide" role="group" aria-label="Slide <?=$i+1?> of <?=count($banners)?>">
        <img
          src="<?=e($imgSrc)?>"
          alt="<?=e($b['title']?:'Aapki Grocery — Fresh from the farm')?>"
          <?= $i===0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
          width="1440" height="480"
          onerror="this.onerror=null;this.src='<?=e($fallback)?>';">
        <?php if($b['title']||$b['subtitle']): ?>
        <div class="hero-slide-copy">
          <?php if($b['title']): ?><h2><?=e($b['title'])?></h2><?php endif; ?>
          <?php if($b['subtitle']): ?><p><?=e($b['subtitle'])?></p><?php endif; ?>
          <?php if($b['link']): ?>
            <a class="hero-cta" href="<?=e($b['link'])?>">Shop now →</a>
          <?php else: ?>
            <a class="hero-cta" href="#products">Shop now →</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if(count($banners)>1): ?>
    <!-- Prev / Next arrows -->
    <button class="slider-btn prev" id="sliderPrev" aria-label="Previous slide">&#8249;</button>
    <button class="slider-btn next" id="sliderNext" aria-label="Next slide">&#8250;</button>

    <!-- Dot indicators -->
    <div class="slider-dots" id="sliderDots" role="tablist" aria-label="Slide indicators">
      <?php foreach($banners as $i=>$b): ?>
        <button class="slider-dot <?=$i===0?'active':''?>"
                role="tab" aria-selected="<?=$i===0?'true':'false'?>"
                aria-label="Go to slide <?=$i+1?>"
                data-index="<?=$i?>"></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
  <!-- Auto-play progress bar -->
  <?php if(count($banners)>1): ?>
  <div class="slider-progress"><div class="slider-progress-bar" id="sliderProgressBar"></div></div>
  <?php endif; ?>
</section>
<?php if(count($banners)>1): ?>
<script>
(function(){
  var slider = document.getElementById('heroSlider');
  if(!slider) return;
  var track  = document.getElementById('heroTrack');
  if(!track) return;
  var slides = track.querySelectorAll('.hero-slide');
  var total  = slides.length;
  if(total < 2) return;

  var prevBtn = document.getElementById('sliderPrev');
  var nextBtn = document.getElementById('sliderNext');
  var dots    = document.querySelectorAll('#sliderDots .slider-dot');
  var bar     = document.getElementById('sliderProgressBar');
  var DELAY   = 5000;  /* 5 seconds */
  var cur     = 0;
  var timer   = null;

  /* ── Move to slide n ──────────────────────────────── */
  function goTo(n) {
    cur = ((n % total) + total) % total;
    track.style.transform = 'translateX(-' + (cur * 100) + '%)';
    dots.forEach(function(d, i) {
      d.classList.toggle('active', i === cur);
      d.setAttribute('aria-selected', String(i === cur));
    });
    startBar();
  }

  /* ── Progress bar ─────────────────────────────────── */
  function startBar() {
    if (!bar) return;
    bar.style.transition = 'none';
    bar.style.width = '0%';
    /* force reflow so transition reset takes effect */
    void bar.offsetWidth;
    bar.style.transition = 'width ' + DELAY + 'ms linear';
    bar.style.width = '100%';
  }

  /* ── Auto-play ────────────────────────────────────── */
  function resetTimer() {
    clearInterval(timer);
    timer = setInterval(function() { goTo(cur + 1); }, DELAY);
  }

  /* ── Controls ─────────────────────────────────────── */
  if (prevBtn) prevBtn.addEventListener('click', function() { goTo(cur - 1); resetTimer(); });
  if (nextBtn) nextBtn.addEventListener('click', function() { goTo(cur + 1); resetTimer(); });
  dots.forEach(function(d) {
    d.addEventListener('click', function() { goTo(+d.dataset.index); resetTimer(); });
  });

  /* ── Swipe ────────────────────────────────────────── */
  var tx = 0, td = 0;
  slider.addEventListener('touchstart', function(e){ tx = e.touches[0].clientX; td = 0; }, {passive:true});
  slider.addEventListener('touchmove',  function(e){ td = e.touches[0].clientX - tx; },    {passive:true});
  slider.addEventListener('touchend',   function(){
    if (Math.abs(td) > 40) { goTo(td < 0 ? cur + 1 : cur - 1); resetTimer(); }
  });

  /* ── Pause when browser tab is hidden ─────────────── */
  document.addEventListener('visibilitychange', function() {
    if (document.hidden) { clearInterval(timer); bar && (bar.style.transition='none'); }
    else                 { goTo(cur); resetTimer(); }
  });

  /* ── Boot ─────────────────────────────────────────── */
  goTo(0);
  resetTimer();
})();
</script>
<?php endif; ?>
<?php endif; ?>
<section class="container categories"><div class="section-head"><div><span class="eyebrow">SHOP BY CATEGORY</span><h2>Fresh from every aisle</h2></div><div class="aisle-arrows"><button type="button" class="aisle-arrow" data-dir="-1" aria-label="Previous categories">‹</button><button type="button" class="aisle-arrow" data-dir="1" aria-label="Next categories">›</button></div></div><div class="cat-scroller" id="categoryScroller"><?php foreach($cats as $c): ?><a class="cat-card" href="<?=url($c['slug'].'/#products')?>"><div class="cat-photo"><img loading="lazy" decoding="async" src="<?=e(asset_url($c['image']??null))?>" alt="<?=e($c['name'])?>" onerror="this.onerror=null;this.src='<?=e(url('assets/images/categories/category-placeholder.svg'))?>';"></div><b><?=e($c['name'])?></b><span>Explore →</span></a><?php endforeach; ?></div></section>
<?php if($catRow): ?>
<section class="category-banner-wrap"><div class="container"><div class="category-banner"><img src="<?=e($catRow['slug']==='masala-spices'?url('assets/images/banners/masala-spices.svg'):url('assets/images/banners/hero-farm-home.svg'))?>" alt="<?=e($catRow['name'])?>"></div></div></section>
<?php endif; ?>
<section id="products" class="container products-section <?= $catRow?'category-layout':'' ?>">
<?php if($catRow): ?>
<aside class="category-sidebar">
  <div class="sidebar-title">Categories</div>
  <div class="sidebar-cats">
    <?php foreach($cats as $c):
      $isActiveParent = $c['slug']===$catRow['slug'] || $c['id']===$catRow['parent_id'];
      $mySubs = $subCats[$c['id']] ?? [];
    ?>
      <a class="<?=$isActiveParent?'active':''?>" href="<?=url($c['slug'].'/#products')?>">
        <?=e($c['name'])?><span>›</span>
      </a>
      <?php if($mySubs && $isActiveParent): ?>
        <div class="sidebar-subcats">
          <?php foreach($mySubs as $sc): ?>
            <a class="sidebar-subcat <?=$sc['slug']===$catRow['slug']?'active':''?>"
               href="<?=url($sc['slug'].'/#products')?>">
              └ <?=e($sc['name'])?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</aside>
<?php endif; ?>
<div class="category-results"><div class="section-head"><div><span class="eyebrow"><?= $catRow?'CATEGORY':'TODAY\'S PICKS' ?></span><h2><?=e($catRow['name']??($q?'Search results':'Fresh picks for your home'))?></h2></div><div class="sort-wrap"><span class="result-count"><?=number_format($total)?> products</span><select id="sortSelect"><option value="featured" <?=$sort==='featured'?'selected':''?>>Recommended</option><option value="price_low" <?=$sort==='price_low'?'selected':''?>>Price: Low to High</option><option value="price_high" <?=$sort==='price_high'?'selected':''?>>Price: High to Low</option><option value="name" <?=$sort==='name'?'selected':''?>>Name</option><option value="new" <?=$sort==='new'?'selected':''?>>Newest</option></select></div></div><div id="productGrid" class="product-grid"><?php include __DIR__.'/includes/product_cards.php'; ?></div><?php if($offset+$limit<$total): ?><div id="loadMore" data-page="2" data-cat="<?=e($cat)?>" data-q="<?=e($q)?>" class="load-more"><button class="btn">Loading more on scroll…</button></div><?php endif; ?></div><?php if($catRow): ?></div><?php endif; ?></section>
<section class="trust-strip"><div class="container trust-grid"><div><div class="trust-icon">🌿</div><b>Fresh First</b><span>Carefully selected everyday produce</span></div><div><div class="trust-icon">🧼</div><b>Hygiene</b><span>Clean packing approach</span></div><div><div class="trust-icon">🚚</div><b>Convenient</b><span>Easy ordering & delivery</span></div><div><div class="trust-icon">🔒</div><b>Secure</b><span>Protected account & checkout</span></div></div></section>
<?php if($popup): $p=$pdo->query("SELECT * FROM popups WHERE is_active=1 AND (start_at IS NULL OR start_at<=NOW()) AND (end_at IS NULL OR end_at>=NOW()) ORDER BY id DESC LIMIT 1")->fetch(); if($p): ?>
<div class="modal" id="sitePopup" style="display:none;" aria-modal="true" role="dialog" aria-labelledby="popupTitle">
  <div class="modal-card popup-card">
    <!-- Auto-close progress bar -->
    <div class="popup-progress"><div class="popup-progress-bar" id="popupProgressBar"></div></div>
    <button class="modal-close" id="popupClose" aria-label="Close popup">×</button>
    <span class="eyebrow">NOTICE</span>
    <h2 id="popupTitle"><?=e($p['title'])?></h2>
    <p><?=nl2br(e($p['message']))?></p>
    <?php if($p['button_url']): ?>
      <a class="btn btn-primary" href="<?=e($p['button_url'])?>"><?=e($p['button_text']?:'Learn more')?></a>
    <?php endif; ?>
    <p class="popup-countdown">Closing in <b id="popupSecs">10</b>s</p>
  </div>
</div>
<script>
(function(){
  var POPUP_ID  = 'popup_seen_<?= (int)$p['id'] ?>';
  var AUTO_SECS = 10;

  // Show only once per browser (localStorage flag)
  if (localStorage.getItem(POPUP_ID)) return;

  var modal   = document.getElementById('sitePopup');
  var bar     = document.getElementById('popupProgressBar');
  var secsEl  = document.getElementById('popupSecs');
  var closeBtn= document.getElementById('popupClose');

  function closePopup() {
    localStorage.setItem(POPUP_ID, '1');
    modal.style.opacity = '0';
    modal.style.transition = 'opacity 0.35s';
    setTimeout(function(){ modal.remove(); }, 360);
  }

  // Show after 600ms so page feels loaded
  setTimeout(function(){
    modal.style.display = 'grid';
    // Trigger reflow so transition plays
    modal.getBoundingClientRect();
    modal.style.opacity = '1';

    // Countdown + progress bar
    var remaining = AUTO_SECS;
    // Animate bar from 100% → 0% over AUTO_SECS seconds
    bar.style.transition = 'width ' + AUTO_SECS + 's linear';
    bar.getBoundingClientRect(); // reflow
    bar.style.width = '0%';

    var tick = setInterval(function(){
      remaining--;
      if (secsEl) secsEl.textContent = remaining;
      if (remaining <= 0) {
        clearInterval(tick);
        closePopup();
      }
    }, 1000);

    // Close button
    closeBtn.addEventListener('click', function(){
      clearInterval(tick);
      closePopup();
    });

    // Click backdrop to close
    modal.addEventListener('click', function(e){
      if (e.target === modal) {
        clearInterval(tick);
        closePopup();
      }
    });

  }, 600);
})();
</script>
<?php endif;endif; ?>
<script>
(function(){
 const sc=document.getElementById('categoryScroller');
 if(sc) document.querySelectorAll('.aisle-arrow').forEach(b=>b.addEventListener('click',()=>sc.scrollBy({left:Number(b.dataset.dir)*320,behavior:'smooth'})));
})();
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
