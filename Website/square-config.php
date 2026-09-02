<?php
/**
 * Square credentials and pricing.

 */

return array(

    // ---------------------------------------------------------------------
    // 1. YOUR SQUARE ACCESS TOKEN  <-- PASTE IT HERE
    //    Square Dashboard > Developer > Credentials > Production Access Token
    // ---------------------------------------------------------------------
    'access_token' => 'EAAAl2BpuxuGcXwtNlNwN1i4YB-hyCsfoMp2RB8vqD_61-a_r7nc7vRqlWXAXchd',

    // ---------------------------------------------------------------------
    // 2. LOCATION ID
    //    Taken from the Square sample you supplied. Note the older index.cjs
    //    used "LWN81JDT73ZMA" - if payments ever land in the wrong place,
    //    check Square Dashboard > Locations.
    // ---------------------------------------------------------------------
    'location_id'  => 'LYZBX8Q6Y1MAK',

    // 'production' for real payments, 'sandbox' for testing with fake cards.
    //
    // The supplied token is a SANDBOX token (verified: it authorises against
    // connect.squareupsandbox.com and returns the "Default Test Account"
    // location LYZBX8Q6Y1MAK, but is UNAUTHORIZED on production).
    // No real money can be taken while this says 'sandbox'.
    //
    // TO GO LIVE: swap in the Production Access Token from
    // Square Dashboard > Developer > Credentials, replace location_id with
    // the real location, then change this to 'production'.
    'environment'  => 'sandbox',

    // ---------------------------------------------------------------------
    // TIER SIGNING SECRET
    //
    // Used to sign which service level (and therefore which price) the
    // customer was actually shown. Without this, the price could be changed
    // by editing the form before submitting.
    //
    // CHANGE THIS to a long random string of your own before going live.
    // Anything else in this file staying secret depends on it.
    // ---------------------------------------------------------------------
    'tier_secret'  => '1f00f02bba7147ab8daacb8e1849659d9092d402d6d87e461424c9f6a66a6c5c',

    // ---------------------------------------------------------------------
    // 3. PRICES, IN PENCE (1000 = £10.00)
    //
    //    These match the prices advertised on the website, because that is
    //    what the customer has been quoted.
    //
    //    !! DISCREPANCY TO RESOLVE !!
    //    The old BookingPage.pl used DIFFERENT amounts:
    //        1 Hr/Sameday  Perl: 23000 (£230)   website: £130
    //        Next day      Perl: 17200 (£172)   website: £110
    //        Routine       Perl:  9500 (£95)    website: £95   (agrees)
    //        VIP/OAP       Perl:  6500 (£65)    website: £65 + VAT
    //    Charging more than the advertised price would be a serious problem,
    //    so the website prices are used here. Correct these if the Perl
    //    figures were the intended ones.
    //
    //    Note: VIP is advertised "£65 plus VAT", so the customer pays
    //    £65 + 20% = £78.00 (7800). The other three are VAT inclusive.
    // ---------------------------------------------------------------------
    'prices' => array(
        '1hr'     => array('amount' => 13000, 'label' => '1 Hr / Sameday / 24 Hr Response'),
        'nextday' => array('amount' => 11000, 'label' => 'Next Day Response (8am-8pm)'),
        'routine' => array('amount' =>  9500, 'label' => 'Routine Service / Safety Check'),
        'vip'     => array('amount' =>  7800, 'label' => 'VIP / Warranty / OAP (inc VAT)'),
    ),
);
