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
totals worked out again, and an order paid any other way that still has the
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

Packagist reads the version from git tags. `composer.json` also includes
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

The key decides which BriizPay the store talks to, and there is no second
setting that could disagree with it:

| Key | Host |
|---|---|
| `bzp_sk_live_` | `api.briizpay.com` |
| `bzp_sk_sandbox_` | `sandbox.api.briizpay.com`, where no real money moves |
| `bzp_sk_test_` | `dev.api.briizpay.com`, BriizPay's own development environment |

The method is hidden at checkout until a key
in the right format is saved, and for any currency other than GBP.

## Before it is connected

The method is hidden at checkout until a valid key is saved, so an
unconnected install changes nothing for shoppers. It used to be silent as well,
which left a merchant whose developer installed the module looking at a form
asking for an API key with nothing saying what BriizPay is or why they would
want it.

`Model/Onboarding.php` owns that first run and two screens draw it:

- **The config section.** While there is no key,
  `Block/Adminhtml/System/Config/Intro.php` renders an introduction above the
  fields: what the store gains, what the customer does at checkout, and the
  numbered path to a key, with a link to create an account. Once a key is saved
  it disappears.
- **The admin notification.** `Model/Adminhtml/System/Message/NotConfigured.php`
  tells a merchant the module is there at all, since the one most likely to need
  this did not install it themselves. It stops showing as soon as a key is
  saved.

A key saved without a webhook signing secret replaces the introduction with its
own warning, because that configuration takes payments and leaves the orders
unpaid with nothing at the checkout looking wrong.

The copy sits in the model rather than the block because both screens draw it,
and it is the first thing a new merchant reads about the product.

## Changelog

The full history is in [CHANGELOG.md](CHANGELOG.md). The highlights:

- **1.2.0**: Routes to the right BriizPay from the key alone: `bzp_sk_sandbox_`
  keys now reach the BriizPay sandbox, where a store can place a test order
  before going live. Live keys are unaffected, and existing `bzp_sk_test_` keys
  still reach the development environment.
- **1.2.0**: A store that has installed the module but not connected it now gets
  a proper introduction in the config section and an admin notification pointing
  at it, instead of a form asking for an API key with no explanation. A key saved
  without a webhook signing secret now says that orders will stay unpaid until
  the secret is added.

- **1.1.1**: A row of UK bank logos (Barclays, HSBC, NatWest, Monzo) shows
  beside the method's name at checkout, with a **Show bank logos at checkout**
  setting to turn it off. On by default. The logos are trademarks of their
  owners and are shown only to indicate that the customer pays from their own
  bank.

## What it does not do yet

- Refunds from the Magento credit memo screen. Refund from the BriizPay dashboard.
- Hyva or other non-Luma checkouts, which need their own payment renderer.

## Checks

Three gates, all runnable by anyone with the repository.

```bash
composer test      # PHPUnit, 113 tests
composer phpcs     # the Magento coding standard
composer phpstan   # static analysis at level 6 (needs a store, see below)
```

### Tests

`Test/Unit` is an ordinary PHPUnit suite. Inside a store it runs the way every
other module's does:

```bash
bin/magento dev:tests:run unit
```

Standalone, with no Magento installed, it runs too. `magento/framework` needs
repo.magento.com credentials that a repository cannot hold, so
`Test/MagentoStubs.php` stands in for the handful of interfaces the module
touches. Every declaration there is guarded by `interface_exists`, so in a real
store the file declares nothing and the same tests run against the real
framework. That is what lets CI check every push.

What they cover: the webhook signature against a vector produced by the API's
own signing code, pence conversion, the receipt lines, the pay by bank discount
(the same cases as the WooCommerce plugin's, so both give a customer the same
saving), the environment a key routes to, the first-run copy including the
claims it may not make, and the shipped files, from the config default to the
size of the bank logos. The rest is exercised end to end against a Mage-OS
testbed and the BriizPay test environment.

### Coding standard

`phpcs.xml` is the full `Magento2` standard. It passes with no errors and no
warnings.

Four things are excluded, each scoped and with its reason in the file: docblocks
that would restate a typed signature, line length in XML, static methods on the
pure-arithmetic helpers, and two sniffs that misread the test stubs. They are
written as decisions rather than silences, so disagreeing with one is a
three-line change.

### Static analysis

PHPStan at level 6, with no errors.

It is not in CI, because resolving types needs Magento's real classes and those
need credentials. Run it in a store:

```bash
vendor/bin/phpstan analyse -c app/code/BriizPay/PayByBank/phpstan.neon.dist
```

The ignore list in `phpstan.neon.dist` is Magento's own shape, not this
module's: places where the framework's runtime and its type declarations
disagree, and the module follows the runtime because that is what a store
executes. Installing `bitexpert/phpstan-magento` removes the need for most of
them.

## Translations

Every string the module shows is in `i18n/en_US.csv`, and a test fails if one
is added without an entry.

A string passed to `__()` has to be a literal. Magento's extractor reads the
source, so `__($variable)` and `__('a' . 'b')` are both invisible to it and
ship untranslatable. That is not hypothetical here: the discount label and the
whole first-run introduction were written that way and reached a merchant's
screen in English whatever their locale.
