<?php
require_once __DIR__.'/config/db.php';
$pdo=db();
$slug=trim($_GET['slug']??'');

// FAQs have their own dedicated layout.
if($slug==='faqs'){ require __DIR__.'/faqs.php'; exit; }

// Try the CMS pages table first.
$row=null;
try{
    $s=$pdo->prepare('SELECT * FROM pages WHERE slug=? AND is_active=1 LIMIT 1');
    $s->execute([$slug]);
    $row=$s->fetch();
}catch(Throwable $e){ /* table may not exist yet (run migration v10) */ }

if($row){
    $pageTitle=$row['title'];
    $pageBody=$row['body'];
    $title=($row['meta_title']?:$row['title']).' | '.APP_NAME;
    $description=$row['meta_description']?:strip_tags(mb_substr((string)$row['body'],0,160));
}else{
    // Fallback for pages not yet in the DB.
    $fallback=[
        'about-us'=>['About Us','<p>Aapki Grocery brings everyday fruits, vegetables, grains, spices, sweets and dry fruits closer to your home.</p>'],
        'privacy-policy'=>['Privacy Policy','<p>We respect your privacy. Customer data is used only to provide services, support orders and improve the store.</p>'],
        'terms-and-conditions'=>['Terms & Conditions','<p>Orders, prices, stock and delivery slots are subject to availability and applicable law.</p>'],
        'contact-us'=>['Contact Us','<p>Email: admin@aapkigrocery.com<br>Phone: +91 9212153207</p>'],
        'farmers-connect'=>['Farmers Connect','<p>We welcome farmers and growers who want to discuss fresh produce supply and responsible sourcing.</p>'],
    ];
    $x=$fallback[$slug]??['Page not found','<p>The requested page does not exist.</p>'];
    $pageTitle=$x[0]; $pageBody=$x[1];
    $title=$pageTitle.' | '.APP_NAME; $description=strip_tags($pageBody);
}

include __DIR__.'/includes/header.php';
?>
<section class="container page cms-page">
  <div class="cms-head">
    <span class="eyebrow">AAPKI GROCERY</span>
    <h1><?=e($pageTitle)?></h1>
  </div>
  <div class="panel cms-body prose">
    <?= $pageBody ?>
  </div>
</section>
<?php include __DIR__.'/includes/footer.php'; ?>
