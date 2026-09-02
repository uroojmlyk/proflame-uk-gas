<?php
/**
 * Pro Flame Gas Services - booking page
 *
 * Dynamically generated per service level, as per the client's flow diagram:
 *   index.html  ->  booking.php?t=S|N|R|V  ->  Square payment
 *
 * The tier decides the title, the price and whether a date picker is shown.
 * The amount is ALWAYS taken from square-config.php on the server - never
 * from the query string - so a tampered URL cannot change what is charged.
 */
$cfg = @include __DIR__ . '/square-config.php';
if (!is_array($cfg)) { $cfg = array('prices' => array()); }

$TIERS = array(
  'S' => array('key'=>'1hr',     'title'=>'1 Hr / Sameday / 24 Hr',       'resp'=>'1 Hr / Sameday / 24 Hr - £130 inc VAT',
               'blurb'=>'Emergency response, engineers available in as little as 1 hour, day or night.', 'cal'=>false),
  'N' => array('key'=>'nextday', 'title'=>'Next Day Response',            'resp'=>'Next day 8am-8pm 7 days - £110 inc VAT',
               'blurb'=>'Next day, 8am-8pm, 7 days a week. Fast without the emergency premium.', 'cal'=>false),
  'R' => array('key'=>'routine', 'title'=>'Routine Service & Safety Check','resp'=>'Routine Service / Safety Check 3-5 day - £95 inc VAT',
               'blurb'=>'Planned servicing and safety checks on a 3-5 day response. Choose your appointment slot below.', 'cal'=>true),
  'V' => array('key'=>'vip',     'title'=>'VIP / Warranty / OAP',         'resp'=>'VIP / Warranty / OAP / Other - £65 plus VAT',
               'blurb'=>'Discounted rate for VIP customers, warranty work, OAPs and contract clients.', 'cal'=>false),
);

$t = isset($_GET['t']) ? strtoupper(substr($_GET['t'],0,1)) : 'S';
if (!isset($TIERS[$t])) { $t = 'S'; }
$tier = $TIERS[$t];

$amount = isset($cfg['prices'][$tier['key']]['amount']) ? (int)$cfg['prices'][$tier['key']]['amount'] : 0;
$priceStr = '£' . number_format($amount / 100, 2);
$vatNote  = ($t === 'V') ? '£65 + VAT' : 'inc VAT';
$h = function ($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };

// Sign the tier so the server can prove which price the customer was shown.
// The visible "Response Required" dropdown is only a label - it must never
// decide the amount, because anything in the form can be edited before it
// is submitted.
$TIER_SECRET = isset($cfg['tier_secret']) ? $cfg['tier_secret'] : '';
$tierSig = $TIER_SECRET !== '' ? hash_hmac('sha256', $t, $TIER_SECRET) : '';
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $h($tier['title']); ?> Booking | Pro Flame Gas Services</title>
<meta name="description" content="Book a <?php echo $h($tier['title']); ?> with Pro Flame Gas Services. <?php echo $h($priceStr); ?> <?php echo $h($vatNote); ?>. Gas Safe registered engineers covering BA, BS, GL and SN.">
<meta name="robots" content="noindex, follow">
<link rel="icon" href="assets/proflame-logo.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/site.css?v=20260819b">
<style>
  .header.checkout .header-inner,
  header.site.checkout .header-inner{padding:10px 22px;}
  .checkout-help{display:flex;align-items:center;gap:16px;font-size:0.85rem;color:var(--muted);font-weight:600;}
  .checkout-help > svg{width:17px;height:17px;color:var(--success);}
  .checkout-phone{display:inline-flex;align-items:center;gap:7px;color:var(--navy-deep);font-weight:700;white-space:nowrap;}
  .checkout-phone .icon{width:16px;height:16px;color:var(--red);}
  .checkout-phone:hover{color:var(--red);}
  @media(max-width:560px){.checkout-help > span{display:none;}}
  .bp-head{background:linear-gradient(160deg,var(--navy) 0%, var(--navy-deep) 75%);color:#fff;padding:44px 0 40px;}
  .bp-head .wrap{display:flex;gap:26px;align-items:center;justify-content:space-between;flex-wrap:wrap;}
  .bp-head h1{color:#fff;font-size:clamp(1.6rem,3vw,2.3rem);margin-bottom:8px;}
  .bp-head p{color:#c3d3e5;margin:0;max-width:560px;}
  .bp-price{background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.25);border-radius:16px;padding:18px 26px;text-align:center;}
  .bp-price strong{display:block;font-family:'Sora',sans-serif;font-size:2.1rem;color:#fff;}
  .bp-price span{font-size:0.8rem;color:#c3d3e5;font-weight:600;}
  .bp-back{display:inline-flex;align-items:center;gap:7px;color:#c3d3e5;font-size:0.86rem;font-weight:600;margin-bottom:14px;}
  .bp-back:hover{color:#fff;}
  .bp-body{background:var(--bg);padding:52px 0 76px;}
  .bp-card{max-width:860px;margin:0 auto;}
  .bp-card .lead-card{padding:32px;}
  .bp-card .lead-form{grid-template-columns:1fr 1fr 1fr;}
  .bp-card .lead-form .field-full{grid-column:1/-1;}
  .bp-card .lf-more-grid{grid-template-columns:1fr 1fr 1fr;}
  @media(max-width:760px){.bp-card .lead-form,.bp-card .lf-more-grid{grid-template-columns:1fr 1fr;}.bp-card .lead-card{padding:22px;}}
  @media(max-width:460px){.bp-card .lead-form,.bp-card .lf-more-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>

<!-- Top bar -->
<div class="topbar">
  <div class="wrap">
    <div class="badges">
      <span><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> Gas Safe Registered</span>
      <span><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> 98% First Time Fix Rate</span>
      <span><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> 1 Year Warranty on All Works</span>
    </div>
    <div>Covering BA · BS · GL · SN · <a href="tel:+447833722922">07833 722922</a></div>
  </div>
</div>

<!-- Checkout header: logo + phone only, no navigation.
     A payment page should not tempt people to wander off mid-booking. -->
<header class="site checkout">
  <div class="wrap">
    <div class="header-inner">
      <a class="logo" href="index.html"><img src="assets/proflame-logo-long.jpg" alt="Pro Flame Gas Services South West"></a>
      <div class="checkout-help">
        <svg class="icon" viewBox="0 0 24 24"><path d="M12 2 4 5v6c0 5 3.5 9 8 11 4.5-2 8-6 8-11V5l-8-3z"/></svg>
        <span>Secure booking</span>
        <a class="checkout-phone" href="tel:+447833722922">
          <svg class="icon" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          Need help? 07833 722922
        </a>
      </div>
    </div>
  </div>
</header>

<section class="bp-head">
  <div class="wrap">
    <div>
      <a class="bp-back" href="index.html">&larr; Back to the website</a>
      <h1><?php echo $h($tier['title']); ?></h1>
      <p><?php echo $h($tier['blurb']); ?></p>
    </div>
    <div class="bp-price">
      <strong><?php echo $h($priceStr); ?></strong>
      <span><?php echo $h($vatNote); ?></span>
    </div>
  </div>
</section>

<section class="bp-body">
  <div class="wrap">
    <div class="bp-card">
      <div class="lead-card" id="payCard">

        <h3>Your details</h3>
        <p class="sub">We use these for your booking, invoice and any refund, so please check they are right.</p>

          <form class="lead-form" id="bookingForm" method="post" action="send-booking.php" novalidate>
            <div><label for="fnam">First Name *</label><input id="fnam" name="fnam" type="text" placeholder="First name" required></div>
            <div><label for="lnam">Last Name</label><input id="lnam" name="lnam" type="text" placeholder="Last name"></div>
            <div><label for="phon">Phone Number *</label><input id="phon" name="phon" type="tel" placeholder="07xxx xxxxxx" required></div>
            <div><label for="emil">Email</label><input id="emil" name="emil" type="email" placeholder="you@example.com"></div>
            <div><label for="pcod">Postcode *</label><input id="pcod" name="pcod" type="text" placeholder="e.g. BS34" required></div>
            <div><label for="Hnum">House/Flat No. *</label><input id="Hnum" name="Hnum" type="text" placeholder="e.g. 12" required></div>
            <div class="field-full">
              <label for="Serv">Service</label>
              <select id="Serv" name="Serv">
                <option value="Select service">Select service</option>
                <option value="Emergency">Emergency</option>
                <option value="Boiler repair">Boiler repair</option>
                <option value="Boiler service">Boiler service</option>
                <option value="Gas repair/installation">Gas repair/installation</option>
                <option value="Plumbing services">Plumbing services</option>
                <option value="Cooker installation">Cooker installation</option>
                <option value="Appliance Installation">Appliance Installation</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="field-full">
              <label for="Resp">Response Required</label>
              <select id="Resp" name="Resp">
                <option value="Select response">Select response</option>
                <option value="1 Hr / Sameday / 24 Hr - £130 inc VAT">1 Hr / Sameday / 24 Hr (£130 inc VAT)</option>
                <option value="Next day 8am-8pm 7 days - £110 inc VAT">Next day 8am-8pm, 7 days (£110 inc VAT)</option>
                <option value="Routine Service / Safety Check 3-5 day - £95 inc VAT">Routine service / safety check (£95 inc VAT)</option>
                <option value="VIP / Warranty / OAP / Other - £65 plus VAT">VIP / Warranty / OAP (£65 plus VAT)</option>
              </select>
            </div>
            <!-- Oil boiler exclusion: the fixed service rate does not cover oil -->
            <div class="oil-warn field-full" id="oilWarn">
              <svg class="icon" viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>
              <span>This fixed service rate is <strong>not available for an Oil Boiler Service</strong>  it takes around 2 hours and involves a tank check. Please still send this form and we'll quote you separately.</span>
            </div>

            <!-- Calendar: shown only for the 3-5 day Routine option -->
            <div class="field-full cal-wrap" id="calWrap">
              <label>Choose your appointment slot</label>
              <div id="cal-booking"></div>

              <!-- Fallback if Cal.com's embed fails to load inside our page
                   (their own logo/assets can 403 when third-party cookies are
                   blocked in an iframe) - opens their real booking page in its
                   own window instead, and lets the customer tell us the slot
                   they picked so it still reaches the booking summary/email. -->
              <div class="cal-fallback" id="calFallback">
                <p class="cal-fallback-note">
                  Calendar not loading properly? <a href="#" id="calFallbackBtn">Open the booking calendar in a new window</a>.
                </p>
                <div class="cal-fallback-field" id="calManualWrap">
                  <label for="CalSlotManual">Appointment date &amp; time you selected</label>
                  <input type="text" id="CalSlotManual" placeholder="e.g. Wed 20 Aug, 1:00pm">
                </div>
              </div>

              <input type="hidden" name="CalSlot" id="CalSlot">
            </div>
            <details class="lf-more field-full">
              <summary>Appliance details (optional)</summary>
              <div class="lf-more-grid">
                <div><label for="Man">Manufacturer</label><input id="Man" name="Man" type="text" placeholder="Worcester Bosch, Vaillant..."></div>
                <div><label for="Mod">Model</label><input id="Mod" name="Mod" type="text" placeholder="Greenstar, EcoTec..."></div>
                <div class="field-full">
                  <label for="fuel">Fuel Type</label>
                  <select id="fuel" name="fuel">
                    <option value="Natural Gas">Natural Gas</option>
                    <option value="LPG">LPG</option>
                    <option value="Oil">Oil</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
                <div class="field-full">
                  <label for="Flt">Description of fault</label>
                  <textarea id="Flt" name="Flt" rows="3" placeholder="No hot water, F75 fault code etc."></textarea>
                </div>
              </div>
            </details>
            <div class="hp-field" aria-hidden="true">
              <label for="website">Leave this field empty</label>
              <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <?php /* Signed tier - this, not the Resp dropdown, decides the price. */ ?>
            <input type="hidden" name="tier_code" value="<?php echo $h($t); ?>">
            <input type="hidden" name="tier_sig" value="<?php echo $h($tierSig); ?>">
            <div class="field-full">
              <button type="submit" class="btn btn-primary btn-block" id="bookingSubmit">Proceed to Payment</button>
            </div>
            <div class="field-full form-status" id="formStatus" role="status" aria-live="polite"></div>
          </form>

<div class="confirm-step" id="confirmStep">
            <h3>Please check your details</h3>
            <p class="sub">Make sure everything is right before you continue to payment.</p>
            <dl class="cf-rows" id="cfRows"></dl>
            <div class="cf-total" id="cfTotal">
              <span>Amount to pay</span>
              <div><strong id="cfPrice">-</strong><small id="cfPriceNote"></small></div>
            </div>
            <div class="cf-actions">
              <button type="button" class="btn btn-back" id="cfBack">Go back &amp; edit</button>
              <button type="button" class="btn btn-primary" id="cfConfirm">Confirm &amp; Pay</button>
            </div>
            <div class="form-status" id="cfStatus" role="status" aria-live="polite" style="margin-top:12px;"></div>
          </div>

<div class="pay-cards">
          <img src="assets/cards.jpg" alt="Visa, Mastercard, American Express, UnionPay, JCB, Apple Pay, Samsung Pay and Google Pay accepted" loading="lazy">
          <img src="assets/cards2.jpg" alt="Visa, Maestro, Mastercard, Delta, Solo and Visa Electron accepted" loading="lazy">
        </div>

<div class="terms-inline">
        <h3 class="terms-inline-h">What your service includes</h3>
        <div class="terms-grid">
              <div class="terms-card">
                <h3><span class="ico"><svg class="icon" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>What you will receive</h3>
                <p>A Boiler/Gas Fire Service or a Safety Check according to manufacturers instructions and The Gas Safety (Installation &amp; Use) Regulations 2018. These usually take approximately 30 minutes to 1 hour.</p>
                <ul class="terms-list">
                  <li><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>A detailed invoice with payment &amp; contractors' details</li>
                  <li><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>A Safety Certificate</li>
                  <li><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>A Service Report including appliance efficiency</li>
                  <li><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>System Water Quality Test (if requested)</li>
                  <li><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>Any Safety Notification(s)</li>
                </ul>
                <?php /* The Heat Exchanger surcharge only applies to the
                         Routine service and VIP tiers, not to 24hr/Next Day. */ ?>
                <?php if ($t === 'R' || $t === 'V'): ?>
                <div class="terms-note">
                  <svg class="icon" viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                  <span><strong>Please note:</strong> this does not include cleaning of the Heat Exchanger or replacement seals/gaskets/electrodes, as this carries an additional charge (approx £80).</span>
                </div>
                <?php endif; ?>
              </div>

              <div class="terms-card">
                <h3><span class="ico"><svg class="icon" viewBox="0 0 24 24"><path d="M3 12 12 3l9 9"/><path d="M5 10v10h14V10"/></svg></span>Access &amp; potential issues</h3>
                <p>To let our engineer work safely and finish in one visit, please make sure:</p>
                <ul class="terms-list">
                  <li><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>Any cabinets or compartments housing boilers are accessible, with nothing preventing removal of the boiler cover or obstructing the service area directly underneath</li>
                  <li><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>If the boiler flue passes into/through your loft, access for inspection is available  this is a legal requirement</li>
                  <li><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>Any inspection hatches relating to Flame Fired Appliances are available</li>
                  <li><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>Oil Boilers and Gas Fires need a significant amount of free floor space during a service/repair</li>
                  <li><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>A smoke test will be carried out if your Gas Fire has a chimney</li>
                  <li><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>Large dogs are kept behind a closed door (not locked)</li>
                  <li><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>There is clear access to the gas meter</li>
                  <li><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>The appliance can be run at maximum load for 5-10 minutes to complete the service/repair</li>
                  <li><svg class="icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>Any warranty paperwork, or the spanner for the system filter, is left close to the boiler/filter</li>
                </ul>
              </div>
            </div>

        <p class="terms-foot">
              We aim for a smooth and undisruptive visit to your property and greatly appreciate all efforts made to comply with the above.
              <br><br>
              Please read our Terms and Conditions. Payments are processed through Square.com and you will be redirected to their payment platform.
              <br><br>
              <strong>This service rate is not available for an Oil Boiler Service</strong>, as this takes around 2 hours to complete and involves a tank check  please book through our
              <a href="index.html#bookingForm" style="color:var(--red);font-weight:700;">standard query form</a> instead.
            </p>
      </div>

      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="site">
  <div class="wrap">
    <div class="footer-grid">
      <div>
        <img class="flogo" src="assets/proflame-logo.jpg" alt="Pro Flame Gas Services South West">
        <p style="color:#93a9cb;">Fast response emergency plumbers and gas engineers covering the South West. Service, repair, safety checks and installs on all oil, LPG and domestic appliances.</p>
      </div>
      <div>
        <h4>Services</h4>
        <ul>
          <li><a href="index.html#services">Emergency Callouts</a></li>
          <li><a href="index.html#services">Boiler Installation</a></li>
          <li><a href="index.html#services">Boiler Repair</a></li>
          <li><a href="index.html#services">Cooker &amp; Hob Fitting</a></li>
          <li><a href="index.html#services">Safety Checks</a></li>
        </ul>
      </div>
      <div>
        <h4>Company</h4>
        <ul>
          <li><a href="index.html#about">About Us</a></li>
          <li><a href="index.html#our-work">Our Work</a></li>
          <li><a href="index.html#before-after">Before &amp; After</a></li>
          <li><a href="index.html#reviews">Testimonials</a></li>
          <li><a href="index.html#coverage">Areas Covered</a></li>
          <li><a href="index.html#faq">FAQ</a></li>
        </ul>
      </div>
      <div>
        <h4>Contact</h4>
        <ul>
          <li><svg class="icon" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg><a href="tel:+447833722922">07833 722922</a></li>
          <li><svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="m22 6-10 7L2 6"/></svg><a href="mailto:info@proflamegas.co.uk">info@proflamegas.co.uk</a></li>
          <li><svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="m22 6-10 7L2 6"/></svg><a href="mailto:admin@proflamegas.co.uk">admin@proflamegas.co.uk</a></li>
          <li><svg class="icon" viewBox="0 0 24 24"><path d="M12 21s-7-6.1-7-11a7 7 0 0 1 14 0c0 4.9-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>Filton, by the M5/M4 Interchange</li>
          <li><svg class="icon" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>VAT Reg No: 86445775</li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© 2026 Pro Flame Gas Services. All rights reserved.</span>
      <span>Gas Safe Registered · OFTEC Registered · Waste Carriers Licence</span>
    </div>
  </div>
</footer>

<div class="sticky-call">
  <a href="tel:+447833722922"><svg class="icon" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>Call Now on 07833 722922</a>
</div>

<script>
  window.PROFLAME_TIER = {
    code:  <?php echo json_encode($t); ?>,
    resp:  <?php echo json_encode($tier['resp']); ?>,
    price: <?php echo json_encode($priceStr); ?>,
    note:  <?php echo json_encode($vatNote); ?>,
    cal:   <?php echo $tier['cal'] ? 'true' : 'false'; ?>
  };
</script>
<script src="assets/booking.js"></script>
</body>
</html>
