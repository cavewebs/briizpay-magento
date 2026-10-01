# BriizPay pay by bank for Magento 2

`BriizPay_PayByBank`: UK Open Banking payments at the Magento checkout. The
customer chooses their bank, approves the payment in their banking app, and the
money goes straight to the merchant's bank account.

For Magento Open Source and Adobe Commerce 2.4 on the standard Luma-based
checkout, PHP 8.1 to 8.4. Orders in pounds only.

## How it works

1. **Place order.** The order is saved as *Pending Payment* with nothing
   captured (`payment_action` initialize).
2. **Straight to the bank.** The checkout goes to `/briizpay/checkout/redirect`,
   which creates the payment on BriizPay for that order (amount from the order
   total, in pence; reference is the order number) and sends the customer to the
   BriizPay pay link with `start=1`, so they land on choosing their bank without
   a second click. The pay link is stored on the order, so a reload reuses it.
   The order's lines go with it (each item after its discount and with its tax,
   and shipping), so the customer's receipt lists what they bought. Adobe
   Commerce gift cards, store credit and reward points, and the pay by bank
   discount, are lines of their own. Any other difference from the grand total is sent as a Rounding, Other
   discounts or Other charges line, so the lines always add up to the charge.
3. **Paid.** BriizPay sends a signed `payment_request.paid` webhook to
   `/briizpay/webhook`. The module checks the signature, the amount and that the
   payment request id matches the one stored on that order, then invoices the
   order (captured offline) and moves it to *Processing*.
4. **Back at the store.** `/briizpay/checkout/back` asks the BriizPay API for the
   status. Paid goes to the success page (and completes the order if the webhook
   has not yet). Cancelled or expired cancels the order and restores the basket.
   Still pending shows the success page with a note.

Whichever of the webhook and the return page arrives first completes the order;
the other finds it already invoiced and does nothing.

**Pay by bank discount.** Optional, and off until turned on. A percentage or a
fixed amount off for customers who pay by bank, worked out on the items after
coupons, with their tax, without shipping, never more than the items and
rounded to the penny: the same discount the WooCommerce plugin gives. The
method's name at checkout says what it saves ("BriizPay - Pay by bank and save
£1.20"). Choosing the method puts a *Pay by bank discount* line in the order
summary straight away, and choosing another method takes it off again. The line
is not taxable and stays with the order: on the order, invoice and credit memo
in the admin, in the customer's account, in the order emails and the PDFs, and
on the customer's BriizPay receipt. The amount charged is the discounted total.

The discount is decided on the server from the method the order is actually
placed with, never from what the checkout showed. Just before any basket becomes
an order, a basket whose discount does not match its payment method has its
totals worked out again, and an order paid any other way that still carries the
discount is refused. So choosing pay by bank, seeing the saving and then placing
the order with another method gets the full price.

**Logo.** A row of four UK bank logos (Barclays, HSBC, NatWest and Monzo),
bundled with the module, shows beside the method's name at checkout, each 20px
square with 4px rounded corners and a muted "+ more" after them. It tells a
customer at a glance that they pay from their own bank, so the method looks as
established as the other payment methods around it. The images are decorative,
because the title already says pay by bank: each has an empty `alt` and the row
is hidden from assistive technology. The logos are trademarks of their owners,
shown only to indicate that the customer pays from their own bank. They are on
by default. Turn off **Show bank logos at checkout** if your theme already
decorates payment methods; it can be set per store view. The config path is
still `payment/briizpay/show_logo`, so stores that set it for the single logo
keep their choice. It needs the static content deploy like the rest of the
checkout's files.

**Cancelling.** When a BriizPay order is cancelled, by an admin, the return page
or Magento's Pending Payment Order Lifetime cleanup, the module cancels the pay
link on BriizPay so it cannot be paid later.

## Install

With Composer, from the Magento root:

```bash
composer require briizpay/module-paybybank
bin/magento module:enable BriizPay_PayByBank
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy en_GB
bin/magento cache:flush
```

Or without Composer, unzip the release into `app/code/BriizPay/PayByBank` so
that `registration.php` sits directly in that folder, and run the same
`bin/magento` commands. `setup:di:compile` and `setup:static-content:deploy`
are needed in production mode; without the static content deploy the checkout
has no script for the payment method and it does not appear.

Update with `composer update briizpay/module-paybybank` and the same commands.

## Releasing

Packagist reads the version from git tags. `composer.json` also carries
`version`, because the Adobe Commerce Marketplace requires it, so the two must
agree: set `version`, merge, then tag `vX.Y.Z` on that commit.

## Configure

1. In the BriizPay dashboard, open **Integrations, Magento**, enter the store's
   address and connect. You get an API key and a webhook signing secret, shown
   once. The webhook is registered for `https://your-store/briizpay/webhook`.
2. In Magento admin: **Stores, Configuration, Sales, Payment Methods, BriizPay -
   Pay by bank**. Set Enabled to Yes and paste the API key and the signing
   secret. Both are stored encrypted.
3. Optionally, in the same place, set **Offer a discount for paying by bank** to
   Yes and choose a percentage of the order or a fixed amount in pounds.
4. Optionally, set **Show bank logos at checkout** to No.

A key beginning `bzp_sk_test_` uses the BriizPay test environment and moves no
real money; `bzp_sk_live_` is live. The method is hidden at checkout until a key
of the right shape is saved, and for any currency other than GBP.

## Changelog

- **1.1.1**: A row of UK bank logos (Barclays, HSBC, NatWest, Monzo) shows
  beside the method's name at checkout, with a **Show bank logos at checkout**
  setting to turn it off. On by default. The logos are trademarks of their
  owners and are shown only to indicate that the customer pays from their own
  bank.

## What it does not do yet

- Refunds from the Magento credit memo screen. Refund from the BriizPay dashboard.
- Hyva or other non-Luma checkouts, which need their own payment renderer.

## Tests

```bash
php Test/run.php
```

Pins the webhook signature against a vector produced by the API's own signing
code, pence conversion, the receipt lines, and the pay by bank discount's
arithmetic, with the same cases as the WooCommerce plugin's tests, and the
bank logos: the setting's default and admin field, what the checkout is given,
the decorative markup and the shipped images. The rest is
exercised end to end against a Mage-OS testbed and the BriizPay test environment.
