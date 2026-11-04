# Changelog

## 0.1.1

- Joomla Update System: the package registers `https://avdeb.com/joomla/updates.xml`, so new versions show up
  under System → Update → Extensions.
- Direct-access guard (`_JEXEC`) in every library file; library language file; control-character regexes use
  `[[:cntrl:]]` (JED Checker clean).
- Module: no empty gap between the module title and the products.

## 0.1.0

- First version for Joomla 5.4+ / 6.x: `pkg_avdeb` with the AVDEB library, content plugin (`{avdeb-products}`,
  `{avdeb-product}`, `{avdeb-button}`), site module and editor button.
- Grid, carousel, list and spotlight layouts; single product as card, horizontal or hero; button.
- Server-side cache in the Joomla cache with a one-week last-good copy, request budget, per-page de-duplication.
- Plugin settings with connection status, live preview, stats and "Refresh products now" (com_ajax, admin + token).
- Catalog contract, client logic, view models and card CSS ported from `emdash-plugin-avdeb`.
