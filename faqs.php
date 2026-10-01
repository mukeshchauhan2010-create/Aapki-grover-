<?php
require_once __DIR__.'/config/db.php';
$pdo=db();

$faqs=[];
try{
    $s=$pdo->query('SELECT * FROM faqs WHERE is_active=1 ORDER BY sort_order,id');
    $faqs=$s->fetchAll();
}catch(Throwable $e){ /* run migration v10 */ }

// Group by category, preserving first-seen order.
$groups=[];
foreach($faqs as $f){ $groups[$f['category']][]=$f; }

$title='FAQs | '.APP_NAME;
$description='Frequently asked questions about orders, delivery, payments, refunds, wallet and more at Aapki Grocery.';
include __DIR__.'/includes/header.php';

$scroll=trim($_GET['scroll']??'');
function faq_anchor(string $c):string{ return 'faq-'.preg_replace('/[^a-z0-9]+/','-',strtolower($c)); }
?>
<section class="container page faq-page">
  <div class="cms-head"><span class="eyebrow">HELP CENTRE</span><h1>Frequently Asked Questions</h1><p class="hint">Find quick answers about orders, delivery, payments, refunds, wallet and more.</p></div>

  <?php if(!$groups): ?>
    <div class="panel"><p class="hint">FAQs will appear here soon. (Admin: run migration v10 and add FAQs from the admin panel.)</p></div>
  <?php else: ?>
  <div class="faq-layout">
    <!-- Category nav -->
    <aside class="faq-nav panel">
      <div class="faq-nav-title">Categories</div>
      <?php foreach($groups as $cat=>$items): ?>
        <a href="#<?=e(faq_anchor($cat))?>" class="faq-nav-link" data-cat="<?=e(faq_anchor($cat))?>"><?=e($cat)?> <span><?=count($items)?></span></a>
      <?php endforeach; ?>
    </aside>

    <!-- Accordion content -->
    <div class="faq-content">
      <?php foreach($groups as $cat=>$items): ?>
      <section class="faq-group" id="<?=e(faq_anchor($cat))?>">
        <h2 class="faq-group-title"><?=e($cat)?></h2>
        <?php foreach($items as $f): ?>
          <details class="faq-item">
            <summary><?=e($f['question'])?></summary>
            <div class="faq-answer"><?=nl2br(e($f['answer']))?></div>
          </details>
        <?php endforeach; ?>
      </section>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</section>
<script>
(function(){
  // Highlight active category + smooth scroll; honor ?scroll=Category
  var links=document.querySelectorAll('.faq-nav-link');
  function activate(id){links.forEach(function(l){l.classList.toggle('active',l.dataset.cat===id);});}
  links.forEach(function(l){l.addEventListener('click',function(){activate(l.dataset.cat);});});

  var scroll=<?=json_encode($scroll)?>;
  if(scroll){
    var target=document.getElementById('faq-'+scroll.toLowerCase().replace(/[^a-z0-9]+/g,'-'));
    if(target){target.scrollIntoView({behavior:'smooth',block:'start'});activate(target.id);
      // open the first item in that group
      var first=target.querySelector('.faq-item');if(first)first.open=true;}
  }
})();
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
