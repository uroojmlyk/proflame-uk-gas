/* ---------------------------------------------------------------------------
   Pro Flame Gas Services - booking page behaviour

   Flow:  fill in details  ->  check your details  ->  Square payment
   The service level and price come from PROFLAME_TIER, which booking.php
   renders server-side. The amount shown here is for display only - the
   charge is always recalculated by send-booking.php.
   --------------------------------------------------------------------------- */
(function () {
  'use strict';

  var TIER = window.PROFLAME_TIER || {};
  var CAL_LINK = 'sultan1-gmx.co.uk/15min';   // must be username/event-slug

  var form        = document.getElementById('bookingForm');
  if (!form) { return; }
  var submitBtn   = document.getElementById('bookingSubmit');
  var submitLabel = submitBtn ? submitBtn.textContent : 'Continue';
  var status      = document.getElementById('formStatus');
  var confirmStep = document.getElementById('confirmStep');
  var cfRows      = document.getElementById('cfRows');
  var cfPrice     = document.getElementById('cfPrice');
  var cfPriceNote = document.getElementById('cfPriceNote');
  var cfTotal     = document.getElementById('cfTotal');
  var cfBack      = document.getElementById('cfBack');
  var cfConfirm   = document.getElementById('cfConfirm');
  var cfStatus    = document.getElementById('cfStatus');
  var resp        = document.getElementById('Resp');
  var calWrap     = document.getElementById('calWrap');
  var calSlot     = document.getElementById('CalSlot');
  var fuel        = document.getElementById('fuel');
  var oilWarn     = document.getElementById('oilWarn');
  var payUrl      = null;

  // ---- the service level is fixed by the page, not chosen in the form -----
  if (resp) {
    for (var i = 0; i < resp.options.length; i++) {
      if (resp.options[i].value === TIER.resp) { resp.selectedIndex = i; break; }
    }
    // lock it so it always matches the price shown in the header
    resp.setAttribute('readonly', 'readonly');
    resp.style.pointerEvents = 'none';
    resp.style.background = '#eef2f8';
    resp.tabIndex = -1;
  }

  // ---- date picker: only for the Routine tier ----------------------------
  if (calWrap) {
    if (TIER.cal) { calWrap.classList.add('show'); loadCal(); }
    else { calWrap.classList.remove('show'); }
  }

  function loadCal() {
    (function (C, A, L) { var p = function (a, ar) { a.q.push(ar); }; var d = C.document;
      C.Cal = C.Cal || function () { var cal = C.Cal; var ar = arguments;
        if (!cal.loaded) { cal.ns = {}; cal.q = cal.q || []; d.head.appendChild(d.createElement('script')).src = A; cal.loaded = true; }
        if (ar[0] === L) { var api = function () { p(api, arguments); }; var ns = ar[1]; api.q = api.q || [];
          if (typeof ns === 'string') { cal.ns[ns] = cal.ns[ns] || api; p(cal.ns[ns], ar); p(cal, ['initNamespace', ns]); }
          else p(cal, ar); return; } p(cal, ar); };
    })(window, 'https://app.cal.com/embed/embed.js', 'init');

    Cal('init', { origin: 'https://cal.com' });
    Cal('inline', { elementOrSelector: '#cal-booking', calLink: CAL_LINK, layout: 'month_view' });
    Cal('on', {
      action: 'bookingSuccessful',
      callback: function (e) {
        try {
          var b = e.detail && e.detail.data && e.detail.data.booking;
          if (b && calSlot) { calSlot.value = (b.startTime || '') + ' (' + (b.title || 'booking') + ')'; }
        } catch (err) {}
      }
    });
  }

  // ---- calendar fallback --------------------------------------------------
  // Cal.com's embed can fail to load inside our page (their own logo/assets
  // return errors when third-party cookies are blocked in an iframe), which
  // can also stop a booking being completed there. If that happens, open
  // their real booking page in its own window instead, and let the customer
  // tell us the slot they picked so it still reaches the booking summary
  // and the email - the booking is never lost just because the embed failed.
  (function () {
    var fallbackBtn = document.getElementById('calFallbackBtn');
    var manualWrap  = document.getElementById('calManualWrap');
    var manualInput = document.getElementById('CalSlotManual');
    if (!fallbackBtn || !manualWrap || !manualInput || !calSlot) { return; }

    fallbackBtn.addEventListener('click', function (e) {
      e.preventDefault();
      var w = 480, h = 720;
      var left = Math.round((window.screen.width  - w) / 2);
      var top  = Math.round((window.screen.height - h) / 2);
      var features = 'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top +
                      ',resizable=yes,scrollbars=yes';
      window.open('https://cal.com/' + CAL_LINK, 'ProFlameCalendar', features);
      manualWrap.classList.add('show');
      manualInput.focus();
    });

    manualInput.addEventListener('input', function () {
      calSlot.value = manualInput.value.trim();
    });
  })();

  // ---- oil boilers are not covered by the fixed service rate -------------
  if (fuel && oilWarn) {
    var syncOil = function () {
      if (fuel.value === 'Oil') { oilWarn.classList.add('show'); }
      else { oilWarn.classList.remove('show'); }
    };
    fuel.addEventListener('change', syncOil);
    syncOil();
  }

  // ---- helpers -----------------------------------------------------------
  function say(el, msg, ok, base) {
    el.textContent = msg;
    el.className = base + ' form-status show ' + (ok ? 'ok' : 'err');
  }
  function val(id) { var e = document.getElementById(id); return e ? e.value.trim() : ''; }

  // ---- step 1 -> step 2 : build the summary ------------------------------
  form.addEventListener('submit', function (e) {
    e.preventDefault();

    var missing = [];
    [['fnam', 'First name'], ['phon', 'Phone number'], ['pcod', 'Postcode'], ['Hnum', 'House/Flat number']].forEach(function (f) {
      if (!val(f[0])) { missing.push(f[1]); }
    });
    if (missing.length) {
      say(status, 'Please fill in: ' + missing.join(', ') + '.', false, 'field-full');
      return;
    }
    // a booked slot is required for the Routine tier
    if (TIER.cal && calSlot && !calSlot.value) {
      say(status, 'Please choose an appointment slot from the calendar above.', false, 'field-full');
      return;
    }
    status.className = 'field-full form-status';

    var rows = [
      ['Name',        [val('fnam'), val('lnam')].filter(Boolean).join(' ')],
      ['Phone',       val('phon')],
      ['Email',       val('emil')],
      ['Address',     [val('Hnum'), val('pcod')].filter(Boolean).join(', ')],
      ['Service',     (val('Serv') !== 'Select service' ? val('Serv') : '')],
      ['Response',    TIER.resp],
      ['Appointment', calSlot ? calSlot.value : ''],
      ['Appliance',   [val('Man'), val('Mod')].filter(Boolean).join(' ')],
      ['Fuel type',   val('fuel')],
      ['Fault',       val('Flt')]
    ].filter(function (r) { return r[1]; });

    cfRows.innerHTML = rows.map(function (r) {
      var d = document.createElement('div');
      d.textContent = r[1];                       // escape user input
      return '<div class="cf-row"><dt>' + r[0] + '</dt><dd>' + d.innerHTML + '</dd></div>';
    }).join('');

    cfPrice.textContent = TIER.price;
    cfPriceNote.textContent = TIER.note;
    cfTotal.style.display = '';
    cfConfirm.textContent = 'Confirm & Pay ' + TIER.price;

    // send the booking through now, so the office has it even if payment stops
    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending...';

    fetch(form.action, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(form)
    })
    .then(function (r) { return r.json().catch(function () { return { ok: r.ok }; }); })
    .then(function (data) {
      if (!data || !data.ok) {
        say(status, (data && data.error) || 'Sorry, we could not send that. Please call us on 07833 722922.', false, 'field-full');
        return;
      }
      payUrl = data.redirect || null;
      say(status, 'Booking received. Please check your details below.', true, 'field-full');

      if (!payUrl) {
        cfTotal.style.display = 'none';
        cfConfirm.style.display = 'none';
        say(cfStatus, 'Thank you. Your booking has been forwarded to our team and we will contact you to arrange payment.', true, '');
      }

      form.style.display = 'none';
      confirmStep.classList.add('show');
      confirmStep.scrollIntoView({ behavior: 'smooth', block: 'center' });
    })
    .catch(function () {
      say(status, 'Sorry, we could not send that. Please call us on 07833 722922.', false, 'field-full');
    })
    .finally(function () {
      submitBtn.disabled = false;
      submitBtn.textContent = submitLabel;
    });
  });

  // ---- back to editing ---------------------------------------------------
  if (cfBack) {
    cfBack.addEventListener('click', function () {
      confirmStep.classList.remove('show');
      form.style.display = '';
      cfStatus.className = 'form-status';
      form.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(function () {
        try { document.getElementById('fnam').focus({ preventScroll: true }); } catch (e) {}
      }, 650);
    });
  }

  // ---- confirm & pay -------------------------------------------------------
  // Opens Square checkout in its own popup window, per the client's flow
  // diagram ("Open URL window" -> "Payment window / Square.com link").
  // If the browser blocks the popup, we fall back to a manual link rather
  // than leaving the customer stuck with no way to pay.
  if (cfConfirm) {
    var payWinTimer = null;

    function showManualLink(url) {
      var wrap = document.createElement('div');
      wrap.style.marginTop = '10px';
      wrap.innerHTML =
        '<a href="' + url + '" target="_blank" rel="noopener" class="btn btn-primary btn-block">' +
        'Open Payment Window</a>';
      cfStatus.parentNode.insertBefore(wrap, cfStatus.nextSibling);
    }

    cfConfirm.addEventListener('click', function () {
      if (!payUrl) {
        say(cfStatus, 'Payment is not available online yet. Please call us on 07833 722922.', false, '');
        return;
      }

      // capture the button's current label ("Confirm & Pay £95.00") fresh
      // on every click, so restoring it after a closed popup shows the
      // right price rather than a stale placeholder
      var savedLabel = cfConfirm.textContent;

      // clear out any earlier "open manually" link before trying again
      var oldManual = cfStatus.parentNode.querySelector('.btn-block[target="_blank"]');
      if (oldManual && oldManual.parentNode) { oldManual.parentNode.parentNode.removeChild(oldManual.parentNode); }

      var w = 520, h = 720;
      var left = Math.round((window.screen.width  - w) / 2);
      var top  = Math.round((window.screen.height - h) / 2);
      // NOTE: deliberately no "noopener"/"noreferrer" here - those make
      // window.open() always return null per spec, which would make every
      // popup look "blocked" even when it opened fine. We need the real
      // window reference to detect when the customer closes it and to
      // re-focus it. Square is a known, trusted destination for this link.
      var features = 'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top +
                      ',resizable=yes,scrollbars=yes';

      var payWin = null;
      try { payWin = window.open(payUrl, 'ProFlamePayment', features); } catch (e) { payWin = null; }

      if (!payWin) {
        // popup blocked - give the customer a direct link instead
        say(cfStatus, 'Your browser blocked the payment popup. Click below to open it manually.', false, '');
        showManualLink(payUrl);
        return;
      }

      cfConfirm.disabled = true;
      cfConfirm.textContent = 'Payment window opened...';
      say(cfStatus, 'A secure payment window has been opened. Complete your payment there, then you can close this page.', true, '');
      showManualLink(payUrl);

      try { payWin.focus(); } catch (e) {}

      // re-enable the button if the customer closes the payment window
      // without finishing, so they can try again
      if (payWinTimer) { clearInterval(payWinTimer); }
      payWinTimer = setInterval(function () {
        if (payWin.closed) {
          clearInterval(payWinTimer);
          cfConfirm.disabled = false;
          cfConfirm.textContent = savedLabel;
        }
      }, 700);
    });
  }

  // ---- mobile menu (same behaviour as the homepage) ----------------------
  (function () {
    var btn = document.querySelector('.nav-toggle');
    var panel = document.getElementById('mobile-nav');
    if (!btn || !panel) { return; }
    function close() {
      panel.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
      btn.setAttribute('aria-label', 'Open menu');
    }
    btn.addEventListener('click', function () {
      var open = panel.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    });
    panel.addEventListener('click', function (e) { if (e.target.tagName === 'A') close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    window.addEventListener('resize', function () { if (window.innerWidth > 1220) close(); });
  })();
})();
