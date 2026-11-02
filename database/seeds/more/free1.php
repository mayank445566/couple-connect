<?php
require_once __DIR__ . '/_helpers.php';

$couple = ['@couple'];
$yn = ['I have', 'Never'];
$ynHi = ['मैंने किया है', 'कभी नहीं'];

return [
  ['game' => 'truth-or-dare', 'packs' => [
    ['slug' => 'tod-fun', 'title' => 'Fun & Games', 'title_hi' => 'मस्ती और खेल', 'level' => 'easy', 'questions' => cc_tod(<<<'TXT'
T: What is the silliest thing you have ever done to make me laugh? | मुझे हँसाने के लिए तुमने अब तक की सबसे बेवकूफ़ी भरी चीज़ क्या की है?
T: Which of my outfits is your favourite? | मेरी कौन सी ड्रेस तुम्हें सबसे ज़्यादा पसंद है?
T: What nickname do you secretly call me in your head? | तुम मन ही मन मुझे किस नाम से बुलाते हो?
T: What was the first thing you told your friends about me? | मेरे बारे में तुमने अपने दोस्तों को सबसे पहले क्या बताया था?
T: What is one habit of mine you have picked up? | मेरी कौन सी आदत तुमने अपना ली है?
T: When did you last check my profile just to look at my photos? | आख़िरी बार तुमने सिर्फ़ मेरी फ़ोटो देखने के लिए मेरी प्रोफ़ाइल कब खोली थी?
T: Which celebrity do people say I look like? | लोग कहते हैं मैं किस सेलिब्रिटी जैसा/जैसी दिखता/दिखती हूँ?
T: What is the most romantic movie scene you want us to recreate? | फ़िल्म का कौन सा रोमांटिक सीन तुम हमारे साथ दोबारा करना चाहोगे?
T: What did you think when you saw my first message? | मेरा पहला मैसेज देखकर तुमने क्या सोचा था?
T: What food would you share only with me? | कौन सा खाना तुम सिर्फ़ मेरे साथ बाँटोगे?
T: What is your guilty-pleasure song? | तुम्हारा वो गाना कौन सा है जो छुपकर सुनते हो?
T: What is one thing about me that surprised you? | मेरे बारे में किस बात ने तुम्हें हैरान किया?
T: If you could read one of my thoughts right now, what would you want to know? | अगर तुम अभी मेरा एक ख़याल पढ़ सकते, तो क्या जानना चाहते?
T: What was the funniest misunderstanding between us? | हमारे बीच सबसे मज़ेदार ग़लतफ़हमी कौन सी थी?
T: What is the cheesiest pickup line you would use on me? | मुझ पर इस्तेमाल करने के लिए तुम्हारी सबसे फ़िल्मी लाइन कौन सी है?
T: What do you do first when you miss me? | मेरी याद आने पर तुम सबसे पहले क्या करते हो?
T: Which emoji describes our relationship best? | कौन सा इमोजी हमारे रिश्ते को सबसे अच्छे से बताता है?
T: What is a small lie you told me that I never found out about? | कौन सा छोटा सा झूठ तुमने मुझसे बोला जो मुझे कभी पता नहीं चला?
T: Which photo of mine would you print and frame? | मेरी कौन सी फ़ोटो तुम प्रिंट करके फ़्रेम करवाना चाहोगे?
T: What would your parents say about me in one word? | तुम्हारे मम्मी-पापा एक शब्द में मेरे बारे में क्या कहेंगे?
D: Send me a selfie making your best "I miss you" face. | "मुझे तुम्हारी याद आ रही है" वाला सबसे प्यारा चेहरा बनाकर सेल्फ़ी भेजो।
D: Sing the first line of a romantic song in a voice note. | किसी रोमांटिक गाने की पहली लाइन वॉइस नोट में गाकर भेजो।
D: Text me five compliments in under one minute. | एक मिनट के अंदर मुझे पाँच तारीफ़ें मैसेज करो।
D: Describe our first meeting like a news reporter. | हमारी पहली मुलाक़ात को न्यूज़ रिपोर्टर की तरह बताओ।
D: Send me a photo of the view from where you are right now. | अभी तुम जहाँ हो, वहाँ का नज़ारा फ़ोटो खींचकर भेजो।
D: Say "I love you" in three different accents in a voice note. | तीन अलग-अलग अंदाज़ में "आई लव यू" बोलकर वॉइस नोट भेजो।
D: Draw a heart on your hand and send me a photo. | अपने हाथ पर दिल बनाओ और फ़ोटो भेजो।
D: Tell me a joke. If I don't laugh, tell another one. | मुझे एक जोक सुनाओ। अगर मैं न हँसूँ, तो दूसरा सुनाओ।
D: Write a two-line shayari for me right now. | अभी मेरे लिए दो लाइन की शायरी लिखो।
D: Send me the oldest photo of us on your phone. | अपने फ़ोन में हम दोनों की सबसे पुरानी फ़ोटो भेजो।
D: Do a 15-second dance and send it to me. | 15 सेकंड डांस करके वीडियो भेजो।
D: Change my name in your phone to something cute for a day. | एक दिन के लिए अपने फ़ोन में मेरा नाम किसी प्यारे नाम से सेव करो।
D: Record yourself saying what you love most about me. | रिकॉर्ड करके बताओ कि तुम्हें मुझमें सबसे ज़्यादा क्या पसंद है।
D: Send me a screenshot of your most-played song. | अपने सबसे ज़्यादा सुने गए गाने का स्क्रीनशॉट भेजो।
D: Pretend to propose to me in a voice note. | वॉइस नोट में मुझे प्रपोज़ करने का नाटक करो।
D: Use only emojis to tell me about your day. | सिर्फ़ इमोजी में मुझे अपने दिन के बारे में बताओ।
D: Make up a short song with my name in it. | मेरे नाम वाला एक छोटा सा गाना बनाओ।
D: Send me a selfie with your funniest face. | अपने सबसे मज़ेदार चेहरे वाली सेल्फ़ी भेजो।
D: Tell me your favourite memory of us in exactly ten words. | ठीक दस शब्दों में हमारी अपनी पसंदीदा याद बताओ।
D: Send a voice note whispering a secret. | फुसफुसाते हुए एक राज़ वॉइस नोट में भेजो।
TXT)],
    ['slug' => 'tod-real', 'title' => 'Real Talk', 'title_hi' => 'दिल की बात', 'level' => 'medium', 'questions' => cc_tod(<<<'TXT'
T: What is one thing you would change about how we argue? | हमारे झगड़ने के तरीक़े में तुम एक चीज़ क्या बदलना चाहोगे?
T: When have you felt most proud of me? | तुम्हें मुझ पर सबसे ज़्यादा गर्व कब हुआ?
T: What is something you need more of from me? | तुम्हें मुझसे किस चीज़ की और ज़रूरत है?
T: What is a dream you have never said out loud? | कौन सा सपना तुमने कभी किसी से नहीं कहा?
T: When did you last cry and why? | आख़िरी बार तुम कब रोए थे और क्यों?
T: What do you think is our biggest strength as a couple? | एक कपल के रूप में हमारी सबसे बड़ी ताक़त क्या है?
T: What is one thing you are afraid to tell me? | ऐसी कौन सी बात है जो मुझे बताने में तुम्हें डर लगता है?
T: Which moment made you trust me completely? | किस पल के बाद तुमने मुझ पर पूरा भरोसा किया?
T: What does your ideal Sunday with me look like? | मेरे साथ तुम्हारा परफ़ेक्ट रविवार कैसा होगा?
T: What is one thing I said that you still remember? | मेरी कही कौन सी बात तुम्हें अब भी याद है?
T: What makes you feel jealous, honestly? | सच बताओ, तुम्हें किस बात से जलन होती है?
T: How do you know when I am upset? | तुम्हें कैसे पता चलता है कि मैं परेशान हूँ?
T: What is your biggest worry about the future? | भविष्य को लेकर तुम्हारी सबसे बड़ी चिंता क्या है?
T: What part of your past do you want me to understand better? | अपने अतीत का कौन सा हिस्सा तुम चाहते हो कि मैं बेहतर समझूँ?
T: When do you feel most loved by me? | तुम्हें मेरे प्यार का सबसे ज़्यादा एहसास कब होता है?
D: Tell me one thing you have been holding back, kindly. | प्यार से एक ऐसी बात बताओ जो तुम कहने से रुक रहे थे।
D: Send me a voice note thanking me for something specific. | किसी ख़ास बात के लिए मुझे शुक्रिया कहते हुए वॉइस नोट भेजो।
D: Write three promises for this month and send them. | इस महीने के लिए तीन वादे लिखकर भेजो।
D: Describe the future home you imagine for us. | हमारे लिए जिस घर का तुम सपना देखते हो, उसके बारे में बताओ।
D: Plan a date for this week and send me the plan. | इस हफ़्ते के लिए एक डेट प्लान करो और मुझे भेजो।
D: Apologise for something small you never said sorry for. | किसी छोटी सी बात के लिए माफ़ी माँगो जिसके लिए कभी सॉरी नहीं कहा।
D: Tell me what you would write in our wedding card. | हमारी शादी के कार्ड में तुम क्या लिखोगे, बताओ।
D: Record a one-minute message for us to listen to in five years. | पाँच साल बाद सुनने के लिए एक मिनट का मैसेज रिकॉर्ड करो।
D: Share a song that explains how you feel about me today. | एक गाना शेयर करो जो बताए कि आज तुम मेरे लिए क्या महसूस करते हो।
D: Name three things I did this month that made you happy. | इस महीने मेरी तीन बातें बताओ जिनसे तुम खुश हुए।
TXT)],
    ['slug' => 'tod-hard', 'title' => 'No Escape', 'title_hi' => 'बचना मुश्किल', 'level' => 'hard', 'questions' => cc_tod(<<<'TXT'
T: Have you ever compared me to an ex? What did you think? | क्या तुमने कभी मेरी तुलना किसी एक्स से की? क्या सोचा था?
T: What was the moment you almost gave up on us? | वो कौन सा पल था जब तुमने हमारे रिश्ते को लगभग छोड़ ही दिया था?
T: What is a secret you have kept from your family about us? | हमारे बारे में तुमने अपने परिवार से कौन सा राज़ छुपाया है?
T: Which of my friends do you like least, and why? | मेरे किस दोस्त को तुम सबसे कम पसंद करते हो, और क्यों?
T: What is the pettiest thing you got angry at me for? | सबसे छोटी बात कौन सी थी जिस पर तुम मुझसे नाराज़ हुए?
T: What would you never forgive in a relationship? | रिश्ते में ऐसी कौन सी चीज़ है जिसे तुम कभी माफ़ नहीं करोगे?
T: Have you ever read my chats without telling me? | क्या तुमने कभी बिना बताए मेरी चैट पढ़ी है?
T: What is one thing you pretend to like for my sake? | ऐसी कौन सी चीज़ है जिसे तुम सिर्फ़ मेरे लिए पसंद करने का नाटक करते हो?
D: Let me read your last five searches. | मुझे अपनी आख़िरी पाँच सर्च दिखाओ।
D: Send me the most embarrassing photo in your gallery. | अपनी गैलरी की सबसे शर्मनाक फ़ोटो भेजो।
D: Let me post one story on your account. | मुझे अपने अकाउंट पर एक स्टोरी डालने दो।
D: Call me and talk in a funny voice for one minute. | मुझे कॉल करो और एक मिनट मज़ेदार आवाज़ में बात करो।
D: Show me your screen time for today. | मुझे अपना आज का स्क्रीन टाइम दिखाओ।
D: Text your best friend "I think I am in love" and send me a screenshot. | अपने बेस्ट फ़्रेंड को "मुझे लगता है मुझे प्यार हो गया है" लिखो और स्क्रीनशॉट भेजो।
D: Send me a voice note of your most dramatic fake cry. | अपने सबसे ड्रामैटिक नकली रोने का वॉइस नोट भेजो।
TXT)],
  ]],

  ['game' => 'how-well-do-you-know-me', 'packs' => [
    ['slug' => 'hwdykm-deeper', 'title' => 'Deeper', 'title_hi' => 'गहराई से', 'level' => 'medium', 'questions' => cc_cards('question', <<<'TXT'
What is my biggest fear? | मेरा सबसे बड़ा डर क्या है?
What would I save first in a fire? | आग लगने पर मैं सबसे पहले क्या बचाऊँगा/बचाऊँगी?
Who is my closest friend? | मेरा सबसे क़रीबी दोस्त कौन है?
What is my favourite childhood memory? | मेरी बचपन की सबसे प्यारी याद कौन सी है?
What is my dream car? | मेरी ड्रीम कार कौन सी है?
What is my favourite sweet? | मेरी पसंदीदा मिठाई कौन सी है?
What is the one app I open most? | मैं कौन सा ऐप सबसे ज़्यादा खोलता/खोलती हूँ?
What do I do when I can't sleep? | नींद न आने पर मैं क्या करता/करती हूँ?
What is my hidden talent? | मेरा छुपा हुआ टैलेंट क्या है?
What is the best gift I ever received? | मुझे अब तक का सबसे अच्छा तोहफ़ा कौन सा मिला है?
What is my favourite festival? | मेरा पसंदीदा त्योहार कौन सा है? | Diwali; Holi; Eid; Christmas; Navratri | दिवाली; होली; ईद; क्रिसमस; नवरात्रि
How do I like my tea or coffee? | मुझे चाय या कॉफ़ी कैसी पसंद है?
Which do I prefer? | मुझे क्या ज़्यादा पसंद है? | Calls; Texts; Voice notes | कॉल; मैसेज; वॉइस नोट
What is my favourite colour? | मेरा पसंदीदा रंग कौन सा है?
What would I name our first pet? | हमारे पहले पालतू जानवर का नाम मैं क्या रखूँगा/रखूँगी?
What do I order at a dhaba? | ढाबे पर मैं क्या ऑर्डर करता/करती हूँ?
What is my biggest goal this year? | इस साल मेरा सबसे बड़ा लक्ष्य क्या है?
Which teacher did I like most in school? | स्कूल में मुझे कौन सा टीचर सबसे ज़्यादा पसंद था?
What is my favourite word? | मेरा पसंदीदा शब्द कौन सा है?
What makes me angry fastest? | मुझे सबसे जल्दी किस बात पर गुस्सा आता है?
What do I usually do on my birthday? | अपने जन्मदिन पर मैं आमतौर पर क्या करता/करती हूँ?
How many hours do I sleep on a weekend? | वीकेंड पर मैं कितने घंटे सोता/सोती हूँ? | Under 7; 7 to 9; 9 to 11; More than 11 | 7 से कम; 7 से 9; 9 से 11; 11 से ज़्यादा
What is my favourite cuisine? | मुझे कौन सा खाना सबसे ज़्यादा पसंद है? | North Indian; South Indian; Chinese; Italian; Street food | नॉर्थ इंडियन; साउथ इंडियन; चाइनीज़; इटैलियन; स्ट्रीट फ़ूड
What is my favourite thing to do on a rainy day? | बारिश के दिन मुझे क्या करना सबसे ज़्यादा पसंद है?
What scares me more? | मुझे किससे ज़्यादा डर लगता है? | Heights; Darkness; Insects; Being alone | ऊँचाई; अँधेरा; कीड़े; अकेलापन
Which city would I love to live in? | मैं किस शहर में रहना पसंद करूँगा/करूँगी?
What is my comfort show or movie? | मेरा कम्फ़र्ट शो या फ़िल्म कौन सी है?
What do I spend the most money on? | मैं सबसे ज़्यादा पैसे किस पर ख़र्च करता/करती हूँ?
Am I more of a saver or a spender? | मैं बचत करने वाला/वाली हूँ या ख़र्च करने वाला/वाली? | Saver; Spender; A bit of both | बचत करने वाला; ख़र्च करने वाला; थोड़ा दोनों
What would my perfect holiday be? | मेरी परफ़ेक्ट छुट्टी कैसी होगी?
What is my go-to excuse to skip plans? | प्लान कैंसिल करने का मेरा पसंदीदा बहाना क्या है?
What is my favourite smell? | मेरी पसंदीदा ख़ुशबू कौन सी है?
What is the first thing I do in the morning? | सुबह उठकर मैं सबसे पहले क्या करता/करती हूँ? | Check phone; Drink water; Snooze alarm; Pray or meditate | फ़ोन देखना; पानी पीना; अलार्म स्नूज़ करना; पूजा या मेडिटेशन
What is my favourite thing about myself? | मुझे अपने बारे में सबसे अच्छा क्या लगता है?
What would I do with a free day and no phone? | बिना फ़ोन के एक ख़ाली दिन मैं क्या करूँगा/करूँगी?
What kind of music calms me down? | किस तरह का म्यूज़िक मुझे शांत करता है?
Who was my first crush? | मेरा पहला क्रश कौन था?
What is my most-used phrase? | मेरा सबसे ज़्यादा बोला जाने वाला डायलॉग क्या है?
What do I want to learn next? | मैं आगे क्या सीखना चाहता/चाहती हूँ?
What makes me feel instantly better? | किस चीज़ से मेरा मूड तुरंत ठीक हो जाता है?
TXT)],
  ]],

  ['game' => 'who-is-more-likely', 'packs' => [
    ['slug' => 'wiml-everyday', 'title' => 'Everyday Life', 'title_hi' => 'रोज़ की ज़िंदगी', 'level' => 'easy', 'questions' => cc_cards('question', <<<'TXT'
Who is more likely to steal the blanket at night? | रात में कंबल कौन ज़्यादा खींचेगा?
Who is more likely to say "five more minutes" and sleep an hour? | "बस पाँच मिनट" कहकर एक घंटा कौन सोएगा?
Who is more likely to order food at midnight? | आधी रात को खाना कौन ऑर्डर करेगा?
Who is more likely to cry at a wedding? | शादी में कौन रो पड़ेगा?
Who is more likely to talk to strangers? | अनजान लोगों से बात कौन करेगा?
Who is more likely to forget where they kept their phone? | फ़ोन कहाँ रखा, ये कौन भूलेगा?
Who is more likely to win an argument? | बहस में कौन जीतेगा?
Who is more likely to start a fight over the TV remote? | टीवी रिमोट पर झगड़ा कौन शुरू करेगा?
Who is more likely to sing loudly in the car? | कार में ज़ोर से गाना कौन गाएगा?
Who is more likely to get scared during a horror movie? | हॉरर फ़िल्म में कौन डर जाएगा?
Who is more likely to adopt a stray dog? | किसी आवारा कुत्ते को कौन घर ले आएगा?
Who is more likely to plan the whole trip? | पूरी ट्रिप कौन प्लान करेगा?
Who is more likely to be late to their own party? | अपनी ही पार्टी में देर से कौन पहुँचेगा?
Who is more likely to eat the other's fries? | दूसरे के फ़्राइज़ कौन खाएगा?
Who is more likely to become a social media star? | सोशल मीडिया स्टार कौन बनेगा?
Who is more likely to send a long good-morning message? | लंबा गुड मॉर्निंग मैसेज कौन भेजेगा?
Who is more likely to start dancing first at a party? | पार्टी में सबसे पहले डांस कौन शुरू करेगा?
Who is more likely to binge a whole series in one night? | एक रात में पूरी सीरीज़ कौन ख़त्म करेगा?
Who is more likely to keep a secret? | राज़ कौन ज़्यादा अच्छे से रखेगा?
Who is more likely to get lost in a mall? | मॉल में कौन खो जाएगा?
Who is more likely to bargain with a shopkeeper? | दुकानदार से मोलभाव कौन करेगा?
Who is more likely to fall asleep first on a call? | कॉल पर पहले कौन सो जाएगा?
Who is more likely to remember birthdays of all relatives? | सब रिश्तेदारों के जन्मदिन कौन याद रखेगा?
Who is more likely to try a weird new food? | कोई अजीब नया खाना कौन ट्राई करेगा?
Who is more likely to say sorry with food? | खाना खिलाकर सॉरी कौन बोलेगा?
Who is more likely to take 100 photos for one selfie? | एक सेल्फ़ी के लिए 100 फ़ोटो कौन खींचेगा?
Who is more likely to quit their job to travel? | घूमने के लिए नौकरी कौन छोड़ देगा?
Who is more likely to laugh at the wrong moment? | ग़लत मौक़े पर हँसी किसकी छूटेगी?
Who is more likely to be the first to say "I miss you"? | "मुझे तुम्हारी याद आ रही है" पहले कौन कहेगा?
Who is more likely to get angry when hungry? | भूख लगने पर किसे ज़्यादा गुस्सा आएगा?
Who is more likely to forget to reply to a message? | मैसेज का जवाब देना कौन भूलेगा?
Who is more likely to talk in their sleep? | नींद में बात कौन करेगा?
Who is more likely to spend all their salary in a week? | एक हफ़्ते में पूरी सैलरी कौन उड़ा देगा?
Who is more likely to become a great parent? | शानदार मम्मी-पापा कौन बनेगा?
Who is more likely to make the first move after a fight? | झगड़े के बाद पहल कौन करेगा?
Who is more likely to secretly watch cartoons? | छुपकर कार्टून कौन देखेगा?
Who is more likely to fake being sick to skip work? | काम से छुट्टी के लिए बीमार होने का नाटक कौन करेगा?
Who is more likely to plan a surprise trip? | सरप्राइज़ ट्रिप कौन प्लान करेगा?
Who is more likely to keep every little gift? | हर छोटा तोहफ़ा संभालकर कौन रखेगा?
Who is more likely to be stuck on their phone at dinner? | डिनर पर फ़ोन में कौन लगा रहेगा?
Who is more likely to give the best hugs? | सबसे अच्छी झप्पी कौन देगा?
Who is more likely to cook a fancy dinner? | शानदार डिनर कौन बनाएगा?
Who is more likely to spoil our future kids? | हमारे बच्चों को कौन ज़्यादा बिगाड़ेगा?
Who is more likely to write a love letter? | लव लेटर कौन लिखेगा?
Who is more likely to become famous for something silly? | किसी बेवकूफ़ी भरी बात के लिए फ़ेमस कौन होगा?
TXT, $couple)],
  ]],

  ['game' => 'this-or-that', 'packs' => [
    ['slug' => 'tot-more', 'title' => 'Big List', 'title_hi' => 'बड़ी लिस्ट', 'level' => 'easy', 'questions' => cc_cards('question', <<<'TXT'
Pick one | एक चुनें | Mountains; Beach | पहाड़; समुद्र तट
Pick one | एक चुनें | Morning walk; Late night drive | सुबह की सैर; देर रात ड्राइव
Pick one | एक चुनें | Chai; Cold coffee | चाय; कोल्ड कॉफ़ी
Pick one | एक चुनें | Samosa; Momos | समोसा; मोमोज़
Pick one | एक चुनें | Netflix; YouTube | नेटफ़्लिक्स; यूट्यूब
Pick one | एक चुनें | Bollywood; Hollywood | बॉलीवुड; हॉलीवुड
Pick one | एक चुनें | Gol gappe; Chaat | गोलगप्पे; चाट
Pick one | एक चुनें | Summer; Winter | गर्मी; सर्दी
Pick one | एक चुनें | Big party; Small get-together | बड़ी पार्टी; छोटी महफ़िल
Pick one | एक चुनें | Window seat; Aisle seat | खिड़की वाली सीट; गलियारे वाली सीट
Pick one | एक चुनें | Train journey; Road trip | ट्रेन का सफ़र; रोड ट्रिप
Pick one | एक चुनें | Ice cream; Chocolate | आइसक्रीम; चॉकलेट
Pick one | एक चुनें | Early bird; Night owl | जल्दी उठने वाला; देर रात तक जागने वाला
Pick one | एक चुनें | Instagram; WhatsApp | इंस्टाग्राम; व्हाट्सऐप
Pick one | एक चुनें | Comedy; Romance | कॉमेडी; रोमांस
Pick one | एक चुनें | Home food; Restaurant | घर का खाना; रेस्टोरेंट
Pick one | एक चुनें | Jeans; Traditional | जींस; ट्रेडिशनल
Pick one | एक चुनें | Cricket; Football | क्रिकेट; फ़ुटबॉल
Pick one | एक चुनें | Paneer; Chicken | पनीर; चिकन
Pick one | एक चुनें | Long hair; Short hair | लंबे बाल; छोटे बाल
Pick one | एक चुनें | Saving money; Spending on experiences | पैसे बचाना; अनुभवों पर ख़र्च
Pick one | एक चुनें | Surprise gifts; Planned gifts | सरप्राइज़ गिफ़्ट; प्लान किया गिफ़्ट
Pick one | एक चुनें | Hug; Kiss on the forehead | झप्पी; माथे पर किस
Pick one | एक चुनें | Candle dinner; Street food date | कैंडल डिनर; स्ट्रीट फ़ूड डेट
Pick one | एक चुनें | Old songs; New songs | पुराने गाने; नए गाने
Pick one | एक चुनें | Arijit Singh; A. R. Rahman | अरिजीत सिंह; ए. आर. रहमान
Pick one | एक चुनें | Pani puri; Pav bhaji | पानी पुरी; पाव भाजी
Pick one | एक चुनें | Selfie; Someone else takes the photo | सेल्फ़ी; कोई और फ़ोटो खींचे
Pick one | एक चुनें | Thriller; Horror | थ्रिलर; हॉरर
Pick one | एक चुनें | Goa; Manali | गोवा; मनाली
Pick one | एक चुनें | Paris; Maldives | पेरिस; मालदीव
Pick one | एक चुनें | Long call; Long chat | लंबी कॉल; लंबी चैट
Pick one | एक चुनें | Dress up; Stay in pyjamas | तैयार होना; पजामे में रहना
Pick one | एक चुनें | Rose; Sunflower | गुलाब; सूरजमुखी
Pick one | एक चुनें | Biryani; Pizza | बिरयानी; पिज़्ज़ा
Pick one | एक चुनें | Puppy; Kitten | पिल्ला; बिल्ली का बच्चा
Pick one | एक चुनें | Rainy day in; Sunny day out | बारिश में घर पर; धूप में बाहर
Pick one | एक चुनें | Poems; Songs | कविता; गाने
Pick one | एक चुनें | Gym together; Walk together | साथ में जिम; साथ में सैर
Pick one | एक चुनें | Lehenga or sherwani; Western wedding outfit | लहंगा या शेरवानी; वेस्टर्न शादी की ड्रेस
Pick one | एक चुनें | Dinner at home; Fancy restaurant | घर पर डिनर; फ़ैंसी रेस्टोरेंट
Pick one | एक चुनें | Video call; Voice call | वीडियो कॉल; वॉइस कॉल
Pick one | एक चुनें | Mango; Strawberry | आम; स्ट्रॉबेरी
Pick one | एक चुनें | Cuddle; Talk all night | लिपटकर सोना; रात भर बातें
Pick one | एक चुनें | City lights; Starry sky | शहर की रोशनी; तारों भरा आसमान
TXT)],
  ]],

  ['game' => 'never-have-i-ever', 'packs' => [
    ['slug' => 'nhie-more', 'title' => 'More Confessions', 'title_hi' => 'और क़बूलनामे', 'level' => 'easy', 'questions' => cc_cards('statement', <<<'TXT'
Never have I ever pretended to be busy to avoid a call. | मैंने कभी कॉल टालने के लिए बिज़ी होने का नाटक नहीं किया।
Never have I ever practised a conversation in the mirror. | मैंने कभी आईने के सामने बात करने की प्रैक्टिस नहीं की।
Never have I ever re-read our old chats. | मैंने कभी हमारी पुरानी चैट दोबारा नहीं पढ़ी।
Never have I ever gone to the movies alone. | मैं कभी अकेले फ़िल्म देखने नहीं गया/गई।
Never have I ever laughed so hard I cried. | मैं कभी इतना नहीं हँसा/हँसी कि आँसू आ जाएँ।
Never have I ever forgotten someone's name right after meeting them. | मैं कभी किसी से मिलते ही उसका नाम नहीं भूला/भूली।
Never have I ever lied about liking a movie. | मैंने कभी किसी फ़िल्म को पसंद करने का झूठ नहीं बोला।
Never have I ever sent a message to the wrong person. | मैंने कभी ग़लत इंसान को मैसेज नहीं भेजा।
Never have I ever fallen asleep in class or a meeting. | मैं कभी क्लास या मीटिंग में नहीं सोया/सोई।
Never have I ever cooked something that nobody could eat. | मैंने कभी ऐसा खाना नहीं बनाया जिसे कोई खा न सके।
Never have I ever cried over a TV show. | मैं कभी किसी टीवी शो पर नहीं रोया/रोई।
Never have I ever stayed up all night talking to someone. | मैं कभी किसी से बात करते हुए पूरी रात नहीं जागा/जागी।
Never have I ever written a diary. | मैंने कभी डायरी नहीं लिखी।
Never have I ever got lost in my own city. | मैं कभी अपने ही शहर में नहीं खोया/खोई।
Never have I ever pretended to know a song. | मैंने कभी कोई गाना जानने का नाटक नहीं किया।
Never have I ever had a crush on a teacher. | मेरा कभी किसी टीचर पर क्रश नहीं रहा।
Never have I ever danced in the rain. | मैंने कभी बारिश में डांस नहीं किया।
Never have I ever stalked someone's old photos online. | मैंने कभी किसी की पुरानी फ़ोटो ऑनलाइन नहीं खंगालीं।
Never have I ever broken something and hidden it. | मैंने कभी कुछ तोड़कर छुपाया नहीं।
Never have I ever been on a blind date. | मैं कभी ब्लाइंड डेट पर नहीं गया/गई।
Never have I ever sneaked out of the house at night. | मैं कभी रात में चुपके से घर से बाहर नहीं गया/गई।
Never have I ever eaten a whole pizza alone. | मैंने कभी अकेले पूरा पिज़्ज़ा नहीं खाया।
Never have I ever written a love letter. | मैंने कभी लव लेटर नहीं लिखा।
Never have I ever been told off by a stranger. | मुझे कभी किसी अनजान ने नहीं डाँटा।
Never have I ever missed a flight or train. | मेरी कभी फ़्लाइट या ट्रेन नहीं छूटी।
Never have I ever lied to get out of a date. | मैंने कभी डेट से बचने के लिए झूठ नहीं बोला।
Never have I ever cut my own hair. | मैंने कभी ख़ुद अपने बाल नहीं काटे।
Never have I ever been jealous of your friend. | मुझे कभी तुम्हारे किसी दोस्त से जलन नहीं हुई।
Never have I ever imagined our kids' names. | मैंने कभी हमारे बच्चों के नाम नहीं सोचे।
Never have I ever saved a screenshot of your message. | मैंने कभी तुम्हारे मैसेज का स्क्रीनशॉट सेव नहीं किया।
Never have I ever practised my signature with your surname. | मैंने कभी तुम्हारे सरनेम के साथ अपना साइन नहीं किया।
Never have I ever fallen in love at first sight. | मुझे कभी पहली नज़र में प्यार नहीं हुआ।
Never have I ever gone a whole day without my phone. | मैं कभी पूरे दिन फ़ोन के बिना नहीं रहा/रही।
Never have I ever sung karaoke in public. | मैंने कभी सबके सामने कराओके नहीं गाया।
Never have I ever been scared of the dark as an adult. | बड़े होने के बाद मुझे कभी अँधेरे से डर नहीं लगा।
Never have I ever kept a gift I was supposed to give someone else. | मैंने कभी किसी और को देने वाला तोहफ़ा अपने पास नहीं रखा।
Never have I ever cried at the airport. | मैं कभी एयरपोर्ट पर नहीं रोया/रोई।
Never have I ever pretended my phone died. | मैंने कभी फ़ोन की बैटरी ख़त्म होने का बहाना नहीं बनाया।
Never have I ever fallen off a bike or scooter. | मैं कभी बाइक या स्कूटी से नहीं गिरा/गिरी।
Never have I ever said "I love you" first. | मैंने कभी पहले "आई लव यू" नहीं कहा।
Never have I ever made a wish on a shooting star. | मैंने कभी टूटते तारे से कोई ख़्वाहिश नहीं माँगी।
Never have I ever read someone's diary. | मैंने कभी किसी की डायरी नहीं पढ़ी।
Never have I ever got a tattoo or wanted one badly. | मैंने कभी टैटू नहीं बनवाया, न ही उसकी बहुत चाह रही।
Never have I ever been in a fight with a best friend for a week. | मेरी कभी बेस्ट फ़्रेंड से हफ़्ते भर लड़ाई नहीं रही।
Never have I ever planned a surprise that went wrong. | मेरा कोई सरप्राइज़ कभी उल्टा नहीं पड़ा।
TXT, $yn, $ynHi)],
  ]],
];
