<?php
/**
 * Seed content for the `faqs` table — supplied Aapki Grocery FAQ.
 * Each row: [category, question, answer]. Order is preserved.
 */
return [

// ── Order ──
['Order','What are delivery slots?','Delivery slot is your preferred time slot which you chose at the time of ordering, during which you can expect the order to be delivered.'],
['Order','Can I change delivery slot after placing order?','Yes, you can modify it, till the order status is “under processing”. Once an order is processed, then the delivery slot can not be modified, but you can cancel the order – till the time the order is “out for delivery”.'],
['Order','How can I check availability of next slot before placing order?','The delivery slot is visible as a drop down selection on the checkout page of the cart.'],
['Order','How can I cancel an order?','You can cancel an order, till the order status changes to “out for delivery”. Once the order is out for delivery it can’t be cancelled due to perishable nature of product. In case of an urgent request, you can call us on customer care number.'],
['Order','How do I add or remove products after placing my order?','You can modify (add or remove any number of items) the order at any time till it is “processed”. After an order is processed and you need to add more items, you can place a new order for those new items. If you need to remove some items after the order is “processed”, you can cancel the whole order and place a new order for your chosen items. Please note the cancellation is only possible till the order is “out-for-delivery”.'],
['Order','Is it possible to order an item which is out of stock?','We provide the facility of “notify when available”. For a product which is out-of-stock currently, you can request to be notified as soon as it is available. You will receive an SMS and an email when the product is replenished in stock, so that you can place order.'],
['Order','How do I check the current status of my order?','You can visit the “my orders” page of “my account” section on the website. You can see the status of order(s) by clicking on the relevant order(s).'],
['Order','How do I check which items were not available from my order?','Every order which is successfully placed on website will be checked by our team for availability of the items requested. In certain unforeseen circumstances, it is possible that certain items from your order are not available for delivery. In that situation, our executive will call you to inform about the same and we will provide you with the option to cancel the order or deliver it without the items which are unavailable.'],

// ── Refund ──
['Refund','How will I get my money back in case of a cancellation or return? What are the modes of refund?',"You will get your money back via:\ni) Credit to your “My Wallet” account, or\nii) the same mode with which you paid and to the source of payment, based on your preference.\nIf you paid via cash on delivery, we will refund it to your “My Wallet” section."],
['Refund','How long does it take to process a refund?','The credit to your My Wallet balance will be done on the same business day. If you have opted for the refund of balance to the source of original payment, it is processed within 2 working days of cancellation from our end. However, it may take one or two days longer to reflect in your account statement depending upon your bank.'],
['Refund','How can I request a refund on an item if I am not satisfied with quality?',"Our intention is to serve you with quality products. Hence, we have a \"no questions asked return and refund policy\" which enables all our customers to return the products if due to some reason they are not satisfied with the quality or freshness of the product. We will accept the returns and refund the full value of the returned products which, at your option, will be credited to i) your My Wallet balance, or ii) the source of payment.\nYou are welcome to reject any product in which you find any quality issue at the time of delivery.\nReturn Policy Time Limits — Processed food: initiate return within 48 hours of delivery. Bread, Dairy, Fruits & Vegetables: returns only at point of delivery."],
['Refund','What do I do if an item is defective (broken, leaking, expired)?','We will replace it for you or refund the amount to your My Wallet if the replacement is not available. Please let us know within the timeline mentioned in our returns policy.'],
['Refund','I failed to observe the defect at delivery for a fresh product but later realized it was defective. What can I do?','In such cases it becomes very difficult to ascertain if the product was delivered in a defective state or deteriorated after delivery. However, if you could not review the quality at the time of delivery due to an exceptional situation and you truly believe a sub-standard product was delivered, we will consider your request in good faith – please reach out to customer care and we will assist you.'],

// ── Payment ──
['Payment','Are there any other charges or taxes in addition to the price shown? Is GST added to the invoice?','There might be delivery fees associated with your location, which will be shown in the order review page before you confirm the order. GST, applicable as per government regulations, is included in the MRP shown to you on website.'],
['Payment','What is the meaning of cash on delivery?','Cash on delivery means that you can pay for your order at the time of order delivery at your doorstep. It is available in selected locations.'],
['Payment','If I pay by credit card how do I get the amount back for items not delivered?','If we are not able to deliver all the products in your order and you have already paid for them online, the balance amount will be refunded to either i) your “My Wallet” credit balance, or ii) the source of payment within 7 working days.'],
['Payment','The money got deducted from my card but the payment status says “unsuccessful”?','In rare instances, due to networking errors, it can happen that a payment card is deducted while the payment does not go through. Please wait for some time. Normally, the amount gets credited back to your card within a few minutes. If that does not happen, please contact us with the details and we will help you. Please rest assured, we will not retain your payment for which the order has not been placed or fulfilled.'],
['Payment','Is it safe to use my credit/debit card on Aapki Grocery?','Yes it is absolutely safe to use your card on aapkigrocery.com. Our payment gateway is managed by Razorpay which uses a 256 bit encrypted system. We do not store or record any of your credit/debit card data on aapkigrocery.com.'],
['Payment','What is “My Wallet”?','“My Wallet” is an online account associated with your “My Account” section that allows you to have a pre-paid credit amount. You can use this prepaid account credit to shop multiple times without having to pay each time. You can add money using available payment means, receive referral credits, receive refund/cancellation credits, or receive bonus points at our discretion.'],
['Payment','What are the modes of payment?',"You can pay using the following modes:\na. Cash on delivery (active in select locations)\nb. Credit and debit cards (VISA / Mastercard / Amex / Diners)\nc. Net Banking\nd. Cash wallets\ne. UPI"],

// ── Delivery ──
['Delivery','When will I receive my order?','Once you select your products and click checkout you will be prompted to select “date” and “delivery slot”. Your order will be delivered on the date and slot selected by you. If we are unable to deliver during the specified time (due to unforeseen situations) we will contact you and take your approval before attempting delivery on a different date or time slot.'],
['Delivery','How will the delivery be done?','We have a dedicated team of delivery boys who will deliver your order to your doorstep.'],
['Delivery','How do I change the delivery info (address)?','You can change your delivery info (contact number or address) before the order status is “out for delivery”. Visit the “My orders” page in the “My account” section and click on the order you want to edit.'],
['Delivery','How much are the delivery charges?','Delivery is free for order size > INR 999.00. Delivery charges for order size < INR 999.00 are shown on the order confirmation page before payment, and may vary from Rs 40 to Rs 83 depending on your location.'],
['Delivery','Do you deliver in my area?','You can check this by entering your area or pin code of delivery in the “Location” section on the homepage. Alternatively, you can check at the time of order checkout when you enter the address.'],
['Delivery','What if my order delivery gets delayed?','We will attempt to contact you in case of delay and take your approval before attempting a late or changed delivery. You will also have the option to cancel such an order and get a full refund.'],
['Delivery','What is the minimum order for delivery?','The minimum order size on our website is Rs 399.0. We don’t accept orders below this amount.'],
['Delivery','Do you provide same day delivery?','We are currently not accepting same day orders, but we hope to add that capability soon. You can check our “delivery slots available” link on the home page to see the earliest delivery possible.'],
['Delivery','Is Delivery date or slot dependent on the products I order?','Yes. The available delivery date/slot shows the earliest delivery you can choose assuming the product is available. For products with limited availability or sourced from third parties, delivery time can be longer; we will inform you before processing your order via a phone call or message.'],

// ── My Account ──
['My Account','I forgot my password, what should I do?','Click on “forgot password” and enter either your registered email address or registered mobile number. If you enter your email, a reset link will be sent; click it and reset your password. If you enter your phone number, an OTP will be sent to reset your password. For further issues, contact customer care via the “Contact Us” section.'],
['My Account','Can I change my email or phone with which I registered?','You can change your email using the “change email” link in the My Profile section. The mobile number uniquely identifies the customer and cannot be changed. If you want to use a new mobile number, you will have to create a new account — or you can add an alternate phone number in the same account.'],
['My Account','Why am I not getting order related emails?','Please check if you have verified your email by clicking the “Verify email” link in My Profile. Also check the spam section of your email account and mark emails from us as “Not Spam”.'],
['My Account','What is My Account?','My Account is your personal section after you log in. It allows you to track active orders, manage delivery details, see your order history and update your personal details.'],
['My Account','Can I save more than one delivery address in my account?','Yes, you can save multiple delivery addresses. However, all items in a single order can only be delivered to one address. For products going to different addresses, place them as separate orders.'],

// ── Cancellation ──
['Cancellation','How can I cancel an order?','You can cancel an order till the status changes to “out-for-delivery”. Once out-for-delivery it can’t be cancelled due to the perishable nature of the product. For urgent requests, call us on the customer care number in the “Contact Us” section.'],
['Cancellation','Are there any order cancellation charges?','If you cancel before the status changes to “out-for-delivery”, no cancellation fee is charged. If you are not available/reachable at delivery and the order remains undelivered, a cancellation charge equal to the value of the fresh (spoiled) products may apply. If a non-delivery occurs due to our mistake, we will cancel and provide a full refund.'],

// ── General ──
['General','What kind of products do you sell?','Our range includes fresh fruits and vegetables, dry fruits, mushrooms, dairy, bakery, cereals, herbs, spices and other grocery products. You can choose from over 1,000 products expanded regularly based on customer feedback and popularity.'],
['General','Do you deliver to my location?','We deliver in most localities across the cities we are present in. Type your area or pincode in the homepage location section to check if we deliver in your area.'],
['General','How do I register?','Click on the "Login/Signup" link on the homepage, provide basic information (name, email and phone number), choose a password, review the terms & conditions and press submit. After verifying your phone number (using OTP) you are registered and ready to shop.'],
['General','Do I have to necessarily register to shop?','You can surf and add products to the cart without registration, but only registered shoppers can checkout and place orders. Registered members must be logged in at checkout.'],
['General','Can I have multiple accounts for family members with different numbers but a common delivery address?','Yes.'],
['General','Can I have different city addresses under one account and place orders for multiple cities?','Yes, you can place orders for multiple cities. Please note we currently deliver in NCR only (Delhi, Gurgaon, Noida and Ghaziabad).'],
['General','What is Aapki Grocery?','Aapki Grocery is Delhi’s premium and highest quality fresh foods company which enables you to buy online: grocery, bakery, fruits & vegetables and other daily need products.'],
['General','Are there any charges for registration?','No. Registration on aapkigrocery.com is absolutely free. Please read our user terms & conditions for other details.'],

// ── Returns ──
['Returns','What if I have to return items I am not satisfied with?','Please rest assured, we will provide you with a full refund for those items which you return or reject due to a quality issue.'],

// ── Others ──
['Others','Are your products also available in offline stores?','Yes, you can find our products in most premium modern retail stores across NCR such as Le Marche, More, Big Bazaar, Easy Day, Spencer’s etc.'],
['Others','I am a corporate/business. Can I place orders?','Yes, you are welcome. If your order size is > Rs 10,000 for a single order, contact us via the bulk order page and we are happy to provide additional discounts.'],
['Others','I’d like to suggest addition of some products. Who do I contact?','You can contact us via phone, email, or the enquiry form in our Contact Us section.'],
['Others','How are the fruits and vegetables weighed?','Items are weighed to ensure compliance with all metrology (weights and measures) standards. Labels contain net weight / net quantity. Unpacked items are weighed and measured before dispatch to ensure they match your order.'],
['Others','How are the fruits and vegetables packaged?','We operate in an ISO 22000:2018 certified facility, giving due attention to hygiene and quality. Items are sorted and graded on stainless steel surfaces and packaged in various food-grade certified packagings.'],

// ── Customer Related ──
['Customer Related','What are your timings to contact customer service?','You can call us between 08:00 am and 08:00 pm.'],
['Customer Related','How can I give feedback on the quality of an order?','Once an order is delivered, you will receive an SMS with a link to provide feedback. You can also provide feedback via the “my orders” page in “my account” section by clicking the “feedback” button.'],
['Customer Related','How do I contact customer service?','You can email us at admin@aapkigrocery.com or call the number provided under the “Contact Us” section. You can also fill the suggestions/complaints form in the “my account” section.'],

// ── Wallet ──
['Wallet','How can I top-up My Wallet amount?',"You may add money in My Wallet via:\na) Credit cards or Debit Cards\nb) Net Banking\nc) Any other acceptable payment methods on the payments page\nEMIs & Cash on delivery are not acceptable modes for top-up of My Wallet."],
['Wallet','What are the minimum and maximum amounts I can add to My Wallet?','A minimum of Rs 500.0 must be added in a single transaction. A maximum of Rs 10,000.0 can be added in one transaction. The total balance in your wallet can reach a maximum of Rs 10,000.0 at any point. Example: if you already have Rs 3,000.0, you can top-up only up to Rs 7,000.0.'],
['Wallet','Does the amount in My Wallet have any time limit for usage?','No, the amount in My Wallet stays there till used.'],
['Wallet','How can I pay using My Wallet while ordering?','Your balance in My Wallet will automatically be the default mode of payment for your order. If you don’t want to use the wallet balance, you can deselect the “Use My Wallet balance” button on the order confirmation page.'],
['Wallet','I want to send my money back to my Card from My Wallet, can I do it?','Under normal circumstances the entire balance in My Wallet (both Cash and Credits) is non-refundable but fully usable for paying for orders. In exceptional circumstances, if you can establish good reasons, we may allow crediting back ONLY the cash amount to its source of top-up. The credit points balance cannot be redeemed for cash.'],
['Wallet','Can I pay partly from My Wallet and partly by other means?','Yes. By default your My Wallet balance is used as the preferred means for payment. You can change that and use only part or none of the balance, and pay the rest by other means.'],
['Wallet','How can I find out the balance in My Wallet?','Sign in to your user account and go to the My Wallet page, where you can see your My Wallet balance.'],
['Wallet','What is My Wallet?','My Wallet is a digital wallet provided by aapkigrocery.com using which you can transact on the site. The amount consists of money added by you + credits by us − purchases and debits by you. Any amount in My Wallet can only be used to purchase on www.aapkigrocery.com and nowhere else. EMI or Cash on delivery is not supported to top up My Wallet.'],

// ── Refer & Earn ──
['Refer & Earn','I’ve got a referral email/message. How do I use it?','Open the message and click the link; a sign-up window will open — register using this window (the link remains active for 30 days). Complete your first order (minimum Rs 499.0). Once delivered and conditions are met, within 24 hours both you and your friend receive Rs.100 in “My Wallet” credit.'],
['Refer & Earn','Is there an expiry date to my wallet credit?','No, there is no expiry date on the reward amount. Once credited to “My Wallet”, it stays there till used.'],
['Refer & Earn','Where do I get my reward of Rs.100?','There is an inbuilt wallet for every user. Your referral reward is credited there and can be used for any future transactions. The reward is credited within 24 hours of your referral’s successful order delivery.'],
['Refer & Earn','How can I refer my friends?','Login to your account, go to “Refer & Earn” under My Account (or the sidebar on mobile), and invite friends via Email, WhatsApp or SMS. Your friend opens the link and registers using your unique referral code (link active 30 days). On their successful first order delivery, both you and your friend earn Rs.100 wallet credit. If the friend registers without using your link, the referral credit will not be activated.'],
['Refer & Earn','How many friends can I refer?','As many as you want. To ensure users don’t get spammed, kindly send invites only to people you are acquainted with.'],

];
