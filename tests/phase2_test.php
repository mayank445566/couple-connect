<?php
declare(strict_types=1);
/**
 * Phase 2 end-to-end test: games, 18+ consent, payments, themes, long-distance hub, phone auth.
 * Needs RAZORPAY_FAKE_ORDERS=true, APP_ENV=local, MAIL_DRIVER=log, SMS_DRIVER=log on the server,
 * and the same RAZORPAY_KEY_SECRET / RAZORPAY_WEBHOOK_SECRET in this environment.
 * Usage: API=http://127.0.0.1:8088 RZP_SECRET=... RZP_WEBHOOK_SECRET=... php tests/phase2_test.php
 */
require __DIR__ . '/lib.php';

$RZP_SECRET = getenv('RZP_SECRET') ?: 'local_test_secret_123';
$RZP_WH = getenv('RZP_WEBHOOK_SECRET') ?: 'local_webhook_secret_456';
$run = bin2hex(random_bytes(3));

[$A, $aId] = makeUser("p2a.$run@example.com", 'Asha');
[$B, $bId] = makeUser("p2b.$run@example.com", 'Ben');
[$C] = makeUser("p2c.$run@example.com", 'Stranger');
pair($A, $B);

function state(string $tok, int $sid): array
{
    return call('GET', "/games/sessions/$sid", null, $tok)['json']['data'];
}

/** Picks a valid answer for the current round from the viewer's side. */
function autoAnswer(array $round, int $myId, int $partnerId, bool $guesser = false): array
{
    $q = $round['question'];
    if ($q['pick_partner']) {
        return ['user_id' => $partnerId];
    }
    if ($q['options']) {
        return ['choice' => $q['options'][0]];
    }
    if ($q['kind'] === 'two_truths') {
        return $guesser ? ['pick' => 1] : ['statements' => ['I can juggle', 'I hate tea', 'I was born in Goa'], 'lie' => 1];
    }
    return ['text' => 'Our first trip to the hills'];
}

echo "Game catalog\n";
$r = call('GET', '/games', null, $A);
$cat = $r['json']['data'];
check('20 free games', count($cat['games']['free']) === 20, count($cat['games']['free']));
check('11 adult games, all locked without premium', count($cat['games']['adult']) === 11 && !array_filter($cat['games']['adult'], fn($g) => !$g['locked']));
check('free games unlocked', !array_filter($cat['games']['free'], fn($g) => $g['locked']));

echo "Match mode: This or That\n";
$r = call('POST', '/games/sessions', ['game' => 'this-or-that', 'rounds' => 3], $A);
check('A invites B', $r['status'] === 201 && $r['json']['data']['status'] === 'invited', $r['json']);
$sid = $r['json']['data']['id'];
$r = call('GET', '/sync?since=0', null, $B);
check('B sync gets game.invited', in_array('game.invited', array_column($r['json']['data']['events'], 'type'), true));
$r = call('POST', "/games/sessions/$sid/join", null, $A);
check('inviter cannot join own invite', $r['status'] === 409);
$r = call('POST', "/games/sessions/$sid/join", null, $B);
$st = $r['json']['data'];
check('B joins, round 1 open with options', $st['status'] === 'active' && $st['round']['round_no'] === 1 && count($st['round']['question']['options']) === 2, $st);
$opt = $st['round']['question']['options'][0];
call('POST', "/games/sessions/$sid/answer", ['answer' => ['choice' => $opt]], $A);
$sb = state($B, $sid);
check('B sees A answered but not the answer', $sb['round']['partner_answered'] && $sb['round']['partner_answer'] === null, $sb['round']);
$r = call('POST', "/games/sessions/$sid/answer", ['answer' => ['choice' => 'Not an option']], $B);
check('invalid choice rejected', $r['status'] === 422);
$r = call('POST', "/games/sessions/$sid/answer", ['answer' => ['choice' => $opt]], $B);
$st = $r['json']['data'];
check('revealed with match and sync point', $st['round']['status'] === 'revealed' && $st['round']['result']['match'] === true && $st['scores']['sync'] === 1, $st);
check('answers visible after reveal', $st['round']['partner_answer'] === ['choice' => $opt]);
$r = call('POST', "/games/sessions/$sid/answer", ['answer' => ['choice' => $opt]], $B);
check('cannot answer twice', $r['status'] === 409);
$r = call('POST', "/games/sessions/$sid/next", null, $A);
check('next round', $r['json']['data']['round']['round_no'] === 2);
$r = call('POST', "/games/sessions/$sid/next", null, $A);
check('cannot skip ahead while round open', $r['status'] === 409);
$r = call('POST', "/games/sessions/$sid/skip", null, $B);
check('skip round', $r['json']['data']['round']['status'] === 'skipped');
call('POST', "/games/sessions/$sid/next", null, $A);
$st = state($A, $sid);
call('POST', "/games/sessions/$sid/answer", ['answer' => ['choice' => $st['round']['question']['options'][0]]], $A);
call('POST', "/games/sessions/$sid/answer", ['answer' => ['choice' => $st['round']['question']['options'][1]]], $B);
$r = call('POST', "/games/sessions/$sid/next", null, $A);
check('session finishes after last round', $r['json']['data']['status'] === 'finished' && isset($r['json']['data']['summary']['compatibility_percent']), $r['json']['data']);
check('compatibility 50% (1 match of 2 played)', ($r['json']['data']['summary']['compatibility_percent'] ?? null) === 50, $r['json']['data']['summary'] ?? null);

echo "Who is more likely (pick a partner)\n";
$sid = call('POST', '/games/sessions', ['game' => 'who-is-more-likely', 'rounds' => 1], $B)['json']['data']['id'];
$st = call('POST', "/games/sessions/$sid/join", null, $A)['json']['data'];
check('question asks to pick one of you', $st['round']['question']['pick_partner'] === true);
call('POST', "/games/sessions/$sid/answer", ['answer' => ['user_id' => $aId]], $A);
$st = call('POST', "/games/sessions/$sid/answer", ['answer' => ['user_id' => $aId]], $B)['json']['data'];
check('both picked Asha: match', $st['round']['result']['match'] === true);
$r = call('POST', "/games/sessions/$sid/answer", ['answer' => ['user_id' => 999999]], $A);
check('picking a stranger rejected', in_array($r['status'], [409, 422], true));

echo "Guess mode: How Well Do You Know Me\n";
$sid = call('POST', '/games/sessions', ['game' => 'how-well-do-you-know-me', 'rounds' => 4], $A)['json']['data']['id'];
$st = call('POST', "/games/sessions/$sid/join", null, $B)['json']['data'];
check('round 1 is about the partner who accepted (B)', $st['round']['subject_id'] === $bId, $st['round']);
$guessed = false;
for ($i = 0; $i < 4 && !$guessed; $i++) {
    $st = state($A, $sid);
    $round = $st['round'];
    $subjectTok = $round['subject_id'] === $aId ? $A : $B;
    $guesserTok = $round['subject_id'] === $aId ? $B : $A;
    $ans = autoAnswer($round, 0, 0);
    call('POST', "/games/sessions/$sid/answer", ['answer' => $ans], $subjectTok);
    $after = call('POST', "/games/sessions/$sid/answer", ['answer' => $ans], $guesserTok)['json']['data'];
    if (!empty($after['round']['result']['needs_judge'])) {
        $r = call('POST', "/games/sessions/$sid/judge", ['correct' => true], $guesserTok);
        check('guesser cannot judge own guess', $r['status'] === 403);
        $j = call('POST', "/games/sessions/$sid/judge", ['correct' => true], $subjectTok)['json']['data'];
        $guesserId = $round['subject_id'] === $aId ? $bId : $aId;
        check('subject judges free-text guess, guesser scores', $j['scores'][(string)$guesserId] >= 1, $j);
        $guessed = true;
    } else {
        $guesserId = $round['subject_id'] === $aId ? $bId : $aId;
        check('matching option guess scores for guesser', $after['round']['result']['correct'] === true && $after['scores'][(string)$guesserId] >= 1, $after);
    }
    call('POST', "/games/sessions/$sid/next", null, $A);
}
call('POST', "/games/sessions/$sid/stop", null, $B);
check('either partner can stop', state($A, $sid)['status'] === 'stopped');

echo "Two Truths and a Lie\n";
$sid = call('POST', '/games/sessions', ['game' => 'two-truths-and-a-lie', 'rounds' => 1], $B)['json']['data']['id'];
call('POST', "/games/sessions/$sid/join", null, $A);
$r = call('POST', "/games/sessions/$sid/answer", ['answer' => ['pick' => 0]], $B);
check('guesser must wait for statements', $r['status'] === 409, $r['json']);
call('POST', "/games/sessions/$sid/answer", ['answer' => ['statements' => ['I can juggle', 'I hate tea', 'I was born in Goa'], 'lie' => 1]], $A);
$sb = state($B, $sid);
check('guesser sees statements, not the lie', $sb['round']['partner_answer'] === ['statements' => ['I can juggle', 'I hate tea', 'I was born in Goa']], $sb['round']);
$st = call('POST', "/games/sessions/$sid/answer", ['answer' => ['pick' => 1]], $B)['json']['data'];
check('correct pick scores', $st['round']['result']['correct'] === true && $st['scores'][(string)$bId] === 1, $st);

echo "Truth or Dare turns\n";
$sid = call('POST', '/games/sessions', ['game' => 'truth-or-dare', 'rounds' => 4], $A)['json']['data']['id'];
$st = call('POST', "/games/sessions/$sid/join", null, $B)['json']['data'];
$r = call('POST', "/games/sessions/$sid/answer", ['answer' => ['done' => true]], $A);
check('not your turn', $r['status'] === 409 && $r['json']['error']['code'] === 'not_your_turn');
$st = call('POST', "/games/sessions/$sid/answer", ['answer' => ['text' => 'Honestly, your smile.']], $B)['json']['data'];
check('subject answer reveals the round', $st['round']['status'] === 'revealed');
$st = call('POST', "/games/sessions/$sid/next", ['kind' => 'dare'], $A)['json']['data'];
check('choose dare', $st['round']['question']['kind'] === 'dare' && $st['round']['subject_id'] === $aId, $st['round']);

echo "Quiz mode: Emoji\n";
$sid = call('POST', '/games/sessions', ['game' => 'emoji-guess', 'rounds' => 1], $A)['json']['data']['id'];
$st = call('POST', "/games/sessions/$sid/join", null, $B)['json']['data'];
$opts = $st['round']['question']['options'];
call('POST', "/games/sessions/$sid/answer", ['answer' => ['choice' => $opts[0]]], $A);
$st = call('POST', "/games/sessions/$sid/answer", ['answer' => ['choice' => $opts[1]]], $B)['json']['data'];
check('quiz reveals correct answer', isset($st['round']['result']['correct_answer']), $st['round']);
$aRight = $opts[0] === $st['round']['result']['correct_answer'];
check('scores follow correctness', ($st['scores'][(string)$aId] === 1) === $aRight);

echo "Love Language summary\n";
$sid = call('POST', '/games/sessions', ['game' => 'love-language-quiz', 'rounds' => 6], $A)['json']['data']['id'];
call('POST', "/games/sessions/$sid/join", null, $B);
for ($i = 0; $i < 6; $i++) {
    $st = state($A, $sid);
    call('POST', "/games/sessions/$sid/answer", ['answer' => ['choice' => $st['round']['question']['options'][1]]], $A);
    call('POST', "/games/sessions/$sid/answer", ['answer' => ['choice' => $st['round']['question']['options'][4]]], $B);
    $last = call('POST', "/games/sessions/$sid/next", null, $A)['json']['data'];
}
check('summary shows top love language per partner', ($last['summary']['top_category'][$aId] ?? '') === 'time' && ($last['summary']['top_category'][$bId] ?? '') === 'touch', $last['summary'] ?? $last);

echo "Custom questions\n";
$r = call('POST', '/games/would-you-rather/custom-questions', ['text' => 'Would you rather…', 'options' => ['Goa trip', 'Manali trip']], $A);
check('add custom question', $r['status'] === 201, $r['json']);
$cq = $r['json']['data']['id'];
$r = call('POST', '/games/would-you-rather/custom-questions', ['text' => 'No options here'], $A);
check('match games need options', $r['status'] === 422);
$r = call('GET', '/games/would-you-rather/custom-questions', null, $B);
check('partner sees custom question', count($r['json']['data']) === 1);
$r = call('GET', '/games/would-you-rather/custom-questions', null, $C);
check('stranger cannot see it', $r['status'] === 409);
$r = call('DELETE', "/games/custom-questions/$cq", null, $B);
check('delete custom question', $r['json']['data']['deleted'] === true);

echo "Daily question and streak\n";
$r = call('GET', '/daily', null, $A);
check('daily question present', !empty($r['json']['data']['question']['text']), $r['json']);
check('same question for both', $r['json']['data']['question']['id'] === call('GET', '/daily', null, $B)['json']['data']['question']['id']);
call('POST', '/daily', ['answer' => 'Your laugh on our call'], $A);
$r = call('GET', '/daily', null, $B);
check('B sees A answered but not the answer', $r['json']['data']['partner_answered'] && $r['json']['data']['partner_answer'] === null);
$r = call('POST', '/daily', ['answer' => 'Your good morning text'], $B);
check('after answering B sees A answer, streak 1', $r['json']['data']['partner_answer'] === 'Your laugh on our call' && $r['json']['data']['streak'] === 1, $r['json']['data']);

echo "18+ consent gate\n";
$r = call('POST', '/games/sessions', ['game' => 'spicy-would-you-rather'], $A);
check('adult game needs consent first', $r['status'] === 403 && $r['json']['error']['code'] === 'adult_consent_required', $r['json']);
$r = call('POST', '/games/adult-consent', ['opt_in' => true], $A);
check('opt-in needs explicit confirmation', $r['status'] === 422);
$r = call('POST', '/games/adult-consent', ['opt_in' => true, 'confirm_adult' => true], $A);
check('A opts in', $r['json']['data']['my_opt_in'] === true && $r['json']['data']['partner_opt_in'] === false, $r['json']);
$r = call('POST', '/games/sessions', ['game' => 'spicy-would-you-rather'], $A);
check('still blocked until partner opts in separately', $r['status'] === 403 && str_contains($r['json']['error']['message'], 'partner'), $r['json']);
call('POST', '/games/adult-consent', ['opt_in' => true, 'confirm_adult' => true], $B);
$r = call('POST', '/games/sessions', ['game' => 'spicy-would-you-rather'], $A);
check('then premium is required', $r['status'] === 402 && $r['json']['error']['code'] === 'premium_required', $r['json']);

echo "Purchase: ₹199 lifetime unlock\n";
$r = call('GET', '/store/products', null, $A);
$prod = $r['json']['data']['products'][0];
check('premium product ₹199, one-time, not owned', $prod['price_paise'] === 19900 && $prod['price_display'] === '₹199' && !$prod['owned'], $prod);
$r = call('POST', '/store/orders', ['sku' => 'premium_lifetime'], $A);
check('order created with public key only', $r['status'] === 201 && $r['json']['data']['checkout']['amount'] === 19900 && !str_contains(json_encode($r['json']), $RZP_SECRET), $r['json']);
$order = $r['json']['data'];
$r2 = call('POST', '/store/orders', ['sku' => 'premium_lifetime'], $A);
check('double tap reuses the same order', $r2['json']['data']['order_id'] === $order['order_id']);
$gwOrder = $order['checkout']['order_id'];
$payId = 'pay_' . bin2hex(random_bytes(7));
$r = call('POST', "/store/orders/{$order['order_id']}/verify", ['razorpay_order_id' => $gwOrder, 'razorpay_payment_id' => $payId, 'razorpay_signature' => 'forged'], $A);
check('forged signature rejected, nothing unlocked', $r['status'] === 400 && !call('GET', '/store/products', null, $A)['json']['data']['products'][0]['owned']);
$r = call('POST', "/store/orders/{$order['order_id']}/verify", ['razorpay_order_id' => $gwOrder, 'razorpay_payment_id' => $payId, 'razorpay_signature' => hash_hmac('sha256', "$gwOrder|$payId", $RZP_SECRET)], $B);
check('partner cannot verify buyer order', $r['status'] === 404);
$sig = hash_hmac('sha256', "$gwOrder|$payId", $RZP_SECRET);
$r = call('POST', "/store/orders/{$order['order_id']}/verify", ['razorpay_order_id' => $gwOrder, 'razorpay_payment_id' => $payId, 'razorpay_signature' => $sig], $A);
check('valid signature unlocks premium + themes', $r['json']['data']['paid'] === true && $r['json']['data']['entitlements']['premium']['owned'] && $r['json']['data']['entitlements']['themes']['owned'], $r['json']);
$r = call('POST', "/store/orders/{$order['order_id']}/verify", ['razorpay_order_id' => $gwOrder, 'razorpay_payment_id' => $payId, 'razorpay_signature' => $sig], $A);
check('verifying again is harmless', $r['status'] === 200);
$r = call('POST', '/store/restore', null, $B);
check('partner shares premium via couple', $r['json']['data']['entitlements']['premium']['owned'] && $r['json']['data']['entitlements']['premium']['via_partner'], $r['json']);
$r = call('POST', '/store/orders', ['sku' => 'premium_lifetime'], $A);
check('buying twice is prevented', $r['status'] === 409);

$body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => $payId, 'order_id' => $gwOrder, 'amount' => 19900, 'currency' => 'INR', 'status' => 'captured']]]]);
$r = call('POST', '/webhooks/razorpay', null, null, ['Content-Type: application/json', 'X-Razorpay-Signature: bad', 'X-Razorpay-Event-Id: evt1' . $run], $body);
check('webhook with bad signature rejected', $r['status'] === 400);
$hdr = ['Content-Type: application/json', 'X-Razorpay-Signature: ' . hash_hmac('sha256', $body, $RZP_WH), 'X-Razorpay-Event-Id: evt1' . $run];
$r = call('POST', '/webhooks/razorpay', null, null, $hdr, $body);
check('signed webhook accepted', $r['status'] === 200 && $r['json']['data']['received'] === true, $r['json']);
$r = call('POST', '/webhooks/razorpay', null, null, $hdr, $body);
check('replayed webhook ignored', $r['json']['data']['duplicate'] === true);
$r = call('GET', '/store/purchases', null, $A);
check('one paid purchase in history', count($r['json']['data']) === 1 && $r['json']['data'][0]['status'] === 'paid' && $r['json']['data'][0]['payment_id'] === $payId, $r['json']);

echo "Webhook-only payment (app closed before verify)\n";
[$D, $dId] = makeUser("p2d.$run@example.com", 'Dee');
$o = call('POST', '/store/orders', ['sku' => 'premium_lifetime'], $D)['json']['data'];
$pay2 = 'pay_' . bin2hex(random_bytes(7));
$body = json_encode(['event' => 'order.paid', 'payload' => ['payment' => ['entity' => ['id' => $pay2, 'order_id' => $o['checkout']['order_id'], 'amount' => 19900, 'currency' => 'INR', 'status' => 'captured']]]]);
call('POST', '/webhooks/razorpay', null, null, ['Content-Type: application/json', 'X-Razorpay-Signature: ' . hash_hmac('sha256', $body, $RZP_WH), 'X-Razorpay-Event-Id: evt2' . $run], $body);
$r = call('POST', '/store/restore', null, $D);
check('webhook alone unlocks premium (restore works)', $r['json']['data']['entitlements']['premium']['owned'] === true);
$bodyBad = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_x' . $run, 'order_id' => 'order_unknown', 'amount' => 100, 'currency' => 'INR']]]]);
$r = call('POST', '/webhooks/razorpay', null, null, ['Content-Type: application/json', 'X-Razorpay-Signature: ' . hash_hmac('sha256', $bodyBad, $RZP_WH), 'X-Razorpay-Event-Id: evt3' . $run], $bodyBad);
check('unknown order in webhook is ignored safely', $r['status'] === 200);

echo "Adult game with premium + consent\n";
$r = call('POST', '/games/sessions', ['game' => 'spicy-would-you-rather', 'level' => 'flirty'], $A);
check('adult game starts', $r['status'] === 201, $r['json']);
$asid = $r['json']['data']['id'];
$r = call('GET', '/sync?since=0', null, $B);
$inv = array_values(array_filter($r['json']['data']['events'], fn($e) => $e['type'] === 'game.invited' && $e['payload']['session_id'] === $asid));
check('adult invite event hides the game name', ($inv[0]['payload']['game'] ?? '') === 'private', $inv);
$st = call('POST', "/games/sessions/$asid/join", null, $B)['json']['data'];
check('adult round shows skip and stop', $st['can_skip'] && $st['can_stop'] && $st['round'] !== null);
$r = call('POST', '/games/sessions', ['game' => 'spicy-would-you-rather', 'level' => 'easy'], $A);
check('adult levels validated', $r['status'] === 422);
call('POST', '/games/adult-consent', ['opt_in' => false], $B);
check('partner opting out stops the adult game', state($A, $asid)['status'] === 'stopped');
$r = call('POST', '/games/sessions', ['game' => 'spicy-would-you-rather'], $A);
check('and blocks new adult games', $r['status'] === 403);

echo "Themes and preferences\n";
$r = call('GET', '/themes', null, $A);
check('14 themes, premium ones unlocked for buyer', count($r['json']['data']['themes']) === 14 && !array_filter($r['json']['data']['themes'], fn($t) => $t['locked']));
$r = call('PATCH', '/me/preferences', ['theme_slug' => 'midnight-love', 'bubble_mine' => '#7B4DFF', 'font_scale' => 1.2, 'quiet_start' => '23:00', 'quiet_end' => '07:00'], $A);
check('apply premium theme + custom colours', $r['json']['data']['theme_slug'] === 'midnight-love' && $r['json']['data']['font_scale'] === 1.2 && $r['json']['data']['notifications']['quiet_start'] === '23:00', $r['json']);
$r = call('PATCH', '/me/preferences', ['bubble_mine' => 'red'], $A);
check('bad colour rejected', $r['status'] === 422);
$r = call('PATCH', '/me/preferences', ['theme_slug' => 'galaxy'], $C);
check('non-premium user cannot use premium theme', $r['status'] === 402);
$r = call('PATCH', '/me/preferences', ['theme_slug' => 'dark'], $C);
check('free theme works for everyone', $r['json']['data']['theme_slug'] === 'dark');

echo "Refund revokes entitlement\n";
$rbody = json_encode(['event' => 'refund.processed', 'payload' => ['refund' => ['entity' => ['id' => 'rfnd_' . $run, 'payment_id' => $payId, 'amount' => 19900]]]]);
call('POST', '/webhooks/razorpay', null, null, ['Content-Type: application/json', 'X-Razorpay-Signature: ' . hash_hmac('sha256', $rbody, $RZP_WH), 'X-Razorpay-Event-Id: evt4' . $run], $rbody);
$r = call('POST', '/store/restore', null, $A);
check('premium revoked after refund', $r['json']['data']['entitlements']['premium']['owned'] === false, $r['json']);
$r = call('PATCH', '/me/preferences', ['theme_slug' => 'romantic-pink'], $A);
check('premium theme locked again', $r['status'] === 402);

echo "Long-distance hub\n";
$r = call('POST', '/taps', ['kind' => 'hug'], $A);
check('send a hug', $r['status'] === 201);
$r = call('GET', '/taps', null, $B);
check('B received the hug', ($r['json']['data']['today']['received']['hug'] ?? 0) === 1 && $r['json']['data']['recent'][0]['from_me'] === false, $r['json']);
$r = call('POST', '/taps', ['kind' => 'slap'], $A);
check('unknown tap rejected', $r['status'] === 422);
call('POST', '/moods', ['mood' => 'missing_you', 'note' => 'Long day'], $B);
$r = call('GET', '/moods', null, $A);
check('A sees B mood', ($r['json']['data'][0]['partner']['mood'] ?? '') === 'missing_you', $r['json']);

$meet = gmdate('Y-m-d\TH:i:s\Z', time() + 12 * 86400);
$r = call('POST', '/hub/countdowns', ['title' => 'Next meeting', 'target_at' => $meet, 'emoji' => '✈️'], $A);
check('create countdown', $r['status'] === 201 && $r['json']['data']['target_at'] === $meet, $r['json']);
$r = call('POST', '/hub/bucket', ['title' => 'See the northern lights'], $B);
$bucketId = $r['json']['data']['id'];
$r = call('PATCH', "/hub/bucket/$bucketId", ['done' => true], $A);
check('mark bucket item done', $r['json']['data']['done'] === true && $r['json']['data']['done_by'] === $aId, $r['json']);
$bday = gmdate('Y-m-d', time() + 5 * 86400);
$r = call('POST', '/hub/calendar', ['title' => 'Mom birthday', 'kind' => 'birthday', 'starts_at' => (date('Y') - 30) . substr($bday, 4) . 'T00:00:00Z', 'all_day' => true, 'repeat_yearly' => true], $A);
check('create yearly calendar event', $r['status'] === 201 && $r['json']['data']['repeat_yearly'] === true, $r['json']);
$r = call('POST', '/hub/memories', ['title' => 'First trip', 'happened_on' => (date('Y') - 1) . date('-m-d'), 'is_milestone' => true], $B);
check('create memory', $r['status'] === 201, $r['json']);
$r = call('POST', '/hub/countdowns', ['title' => 'Bad', 'target_at' => 'not a date'], $A);
check('invalid date rejected', $r['status'] === 422);
$r = call('GET', '/hub/bucket', null, $C);
check('stranger has no hub', $r['status'] === 409);
$r = call('GET', '/hub/secrets', null, $A);
check('unknown hub list 404', $r['status'] === 404);

echo "Letters and journal\n";
$r = call('POST', '/notes', ['kind' => 'letter', 'title' => 'Open when we meet', 'body' => 'I cannot wait to see you.', 'deliver_at' => gmdate('c', time() + 86400)], $A);
check('schedule a love letter', $r['status'] === 201 && $r['json']['data']['deliver_at'] !== null, $r['json']);
$letterId = $r['json']['data']['id'];
$r = call('GET', '/notes', null, $B);
check('B cannot read it yet, sees one sealed letter', !in_array($letterId, array_column($r['json']['data']['notes'], 'id'), true) && $r['json']['data']['sealed_letters_waiting'] === 1, $r['json']);
$r = call('GET', "/notes/$letterId", null, $B);
check('opening early is not possible', $r['status'] === 404);
$r = call('POST', '/notes', ['kind' => 'journal', 'body' => 'Today we laughed for an hour on call.'], $B);
$j = $r['json']['data']['id'];
$r = call('GET', "/notes/$j", null, $A);
check('A opens B journal entry', $r['json']['data']['body'] === 'Today we laughed for an hour on call.');
$r = call('DELETE', "/notes/$j", null, $A);
check('only the author can delete', $r['status'] === 404);

echo "Home dashboard\n";
call('PATCH', '/couple', ['anniversary_date' => date('Y-m-d', strtotime('-1 year +10 days'))], $A);
$r = call('GET', '/home', null, $A);
$h = $r['json']['data'];
check('home has core fields', $r['status'] === 200 && isset($h['days_together'], $h['streak'], $h['taps_today']), $h);
check('home shows countdown, anniversary in 10 days', $h['next_countdown']['title'] === 'Next meeting' && $h['anniversary']['in_days'] === 10, $h);
check('home shows partner mood and memory of the day', $h['partner']['mood_today'] === 'missing_you' && ($h['memory_of_the_day']['title'] ?? '') === 'First trip', $h);
check('home lists upcoming birthday', ($h['upcoming'][0]['title'] ?? '') === 'Mom birthday', $h['upcoming']);

echo "Phone signup and login\n";
$phone = '+9198' . random_int(10000000, 99999999);
$r = call('POST', '/auth/phone/request-otp', ['phone' => '12345']);
check('invalid phone rejected', $r['status'] === 422);
$r = call('POST', '/auth/phone/request-otp', ['phone' => $phone]);
check('OTP sent', $r['json']['data']['sent'] === true);
$r = call('POST', '/auth/phone/register', ['phone' => $phone, 'code' => '000000', 'password' => 'Moonlight2026', 'display_name' => 'Priya', 'dob' => '1997-08-02', 'accept_terms' => true]);
check('wrong OTP rejected', $r['status'] === 400);
$r = call('POST', '/auth/phone/register', ['phone' => $phone, 'code' => (string)lastSms($phone), 'password' => 'Moonlight2026', 'display_name' => 'Priya', 'dob' => '1997-08-02', 'accept_terms' => true]);
check('register with phone', $r['status'] === 201 && $r['json']['data']['user']['phone_verified'] === true && $r['json']['data']['user']['email'] === null, $r['json']);
$P = $r['json']['data']['token'];
$r = call('POST', '/couple/invite', null, $P);
check('phone-verified user can pair', $r['status'] === 201);
$r = call('POST', '/auth/phone/login', ['phone' => $phone, 'password' => 'Moonlight2026']);
check('login with phone + password', $r['status'] === 200);
call('POST', '/auth/phone/request-otp', ['phone' => $phone]);
$r = call('POST', '/auth/phone/login', ['phone' => $phone, 'code' => (string)lastSms($phone)]);
check('login with phone + OTP', $r['status'] === 200 && !empty($r['json']['data']['token']));
$r = call('POST', '/auth/phone/login', ['phone' => $phone, 'password' => 'wrong']);
check('wrong password rejected', $r['status'] === 401);

finish();
