# Changelog

Notable changes to BriizPay Pay by Bank for Magento 2.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this module uses [semantic versioning](https://semver.org/spec/v2.0.0.html).

Packagist reads the version from the git tag, and `composer.json` carries the
same number because the Adobe Commerce Marketplace requires it. Set `version`,
merge, then tag `vX.Y.Z` on that commit.

## [Unreleased]

### Added

- PHPUnit tests under `Test/Unit`, runnable by `bin/magento dev:tests:run unit`
  or `composer test`. They replace a hand-rolled runner whose checks nobody
  else's tooling could find.
- `phpcs.xml` for the Magento coding standard, clean with no errors and no
  warnings, and `phpstan.neon.dist` at level 6 with no errors. Both are
  runnable by a composer script, and every exclusion in either names its
  reason.
- A GitHub Actions workflow running the coding standard and the tests on every
  push and pull request, against each supported PHP version. Static analysis
  needs Magento's own classes and therefore credentials, so it runs in a store
  and README.md gives the command.
- `i18n/en_US.csv`, so every string the module shows can be translated. They
  were marked translatable and had nowhere to be translated to.
- `LICENSE.txt`, naming the OSL-3.0 terms `composer.json` already declared.
- `@api` annotations on the classes another module may extend, marking what is
  safe to depend on.

### Changed

- The admin introduction and webhook panels are `.phtml` templates with the
  admin's own CSS classes, instead of HTML built by string concatenation with
  inline styles. They can now be overridden by a theme like any other block.
- `composer.json` declares every Magento module the code imports.
  `Magento_Backend`, `Magento_Config` and `Magento_Store` were used and not
  required, which a minimal install can resolve without.
- `etc/module.xml` sequences the same set, so this module always loads after
  the ones it extends.
- Translatable strings are literals again. `__()` on a variable or on a
  concatenation is invisible to Magento's extractor, so the discount label and
  the whole first-run introduction were shipping in English whatever the
  store's locale. A test now fails if a string is added without an entry.

### Not done, and why

- **A per-module ACL resource.** `<resource>` is valid on a `<section>`, and
  this module's settings are a `<group>` inside Magento's own `payment`
  section, which is where a payment method belongs. Magento refuses the
  configuration outright if a group names one. Granting a role access to the
  BriizPay settings alone would mean moving them out of the payment section,
  which is worse for the merchant who goes looking for them.

## [1.2.0] - 2026-10

### Added

- A first-run introduction in the configuration section explaining what the
  store gains and how to connect, which disappears once a key is saved.
- Sandbox API keys route to the sandbox API, so a store can test without
  touching live money.

### Changed

- Onboarding copy brought in line with the standing claims rules: no "no
  transaction fees", no "no chargebacks", and settlement described as usually
  within seconds.

## [1.1.1] - 2026-09

### Added

- UK bank logos beside the method name at checkout, so a shopper recognises
  what pay by bank means before choosing it.

## [1.1.0] - 2026-09

### Added

- An optional pay by bank discount, calculated and enforced on the server, with
  matching lines on the invoice, the credit memo and the printed documents.

## [1.0.1] - 2026-08

### Added

- Order lines sent with the payment, so the receipt lists what was bought.
  Gift card and store credit appear as their own named lines, and the lines
  balance to the grand total.

## [1.0.0] - 2026-08

### Added

- Pay by bank at checkout: the payment method, the redirect, the return, and
  the signed `payment_request.paid` webhook that marks an order paid.
- Webhook events recorded by plain insert, with a duplicate key treated as the
  repeat delivery it is.
