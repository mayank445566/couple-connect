<?php
/**
 * 18+ content. Rule: sensual, teasing and consensual, never graphic or explicit,
 * so it stays within Google Play policy. Every card can be skipped by either partner.
 */
require_once __DIR__ . '/_helpers.php';

return [
  ['game' => 'adult-truth-or-dare', 'packs' => [
    ['slug' => 'atod-flirty', 'title' => 'Flirty', 'title_hi' => 'फ़्लर्टी', 'level' => 'flirty', 'questions' => cc_tod(<<<'TXT'
T: What was the first thing about me that made you blush? | मेरी किस बात पर तुम पहली बार शरमाए थे?
T: What is your favourite way I say your name? | मेरा तुम्हारा नाम लेने का कौन सा अंदाज़ तुम्हें सबसे पसंद है?
T: Which of my photos do you look at the most? | मेरी कौन सी फ़ोटो तुम सबसे ज़्यादा देखते हो?
T: What do you notice about me when I walk into a room? | जब मैं कमरे में आता/आती हूँ, तो तुम मुझमें क्या देखते हो?
T: What's the flirtiest thing I've ever done without realising? | मैंने अनजाने में सबसे फ़्लर्टी चीज़ क्या की है?
T: If you had to describe my lips in one word, what would it be? | एक शब्द में मेरे होंठों को कैसे बताओगे?
T: What colour do you love seeing me in most? | मुझे किस रंग में देखना तुम्हें सबसे ज़्यादा पसंद है?
T: When was the last time you wanted to kiss me in public? | आख़िरी बार कब तुम्हें सबके सामने मुझे किस करने का मन हुआ?
T: What's one compliment about my body you've never said aloud? | मेरी बॉडी की कौन सी तारीफ़ तुमने कभी ज़ोर से नहीं कही?
T: What song makes you think of me in a flirty way? | कौन सा गाना तुम्हें फ़्लर्टी अंदाज़ में मेरी याद दिलाता है?
T: Do you prefer my morning look or my dressed-up look? | तुम्हें मेरा सुबह वाला लुक पसंद है या सजा-धजा?
T: What is the cutest thing I do when I'm sleepy? | नींद में मैं सबसे प्यारी चीज़ क्या करता/करती हूँ?
D: Send me a voice note saying "good night" in your softest voice. | अपनी सबसे नर्म आवाज़ में "गुड नाइट" कहते हुए वॉइस नोट भेजो।
D: Send a selfie biting your lip. | होंठ दबाते हुए एक सेल्फ़ी भेजो।
D: Text me three things you'd do if I were next to you right now. | मैसेज करो कि अगर मैं अभी तुम्हारे पास होता/होती तो तुम कौन सी तीन चीज़ें करते।
D: Wink at me on video call for five seconds straight. | वीडियो कॉल पर लगातार पाँच सेकंड मुझे आँख मारो।
D: Describe your favourite outfit of mine in detail. | मेरी पसंदीदा ड्रेस को विस्तार से बताओ।
D: Send me a photo of just your smile. | सिर्फ़ अपनी मुस्कान की फ़ोटो भेजो।
D: Whisper my name three times in a voice note. | वॉइस नोट में तीन बार फुसफुसाकर मेरा नाम लो।
D: Blow me a kiss on video and make it dramatic. | वीडियो पर ड्रामैटिक अंदाज़ में मुझे फ़्लाइंग किस दो।
D: Write me a flirty one-line text I'll read at work tomorrow. | एक फ़्लर्टी लाइन लिखो जो मैं कल काम पर पढ़ूँगा/पढ़ूँगी।
D: Change your wallpaper to my most attractive photo for a day. | एक दिन के लिए मेरी सबसे आकर्षक फ़ोटो को वॉलपेपर बनाओ।
D: Tell me your favourite thing about my voice, in a whisper. | फुसफुसाकर बताओ मेरी आवाज़ की कौन सी बात तुम्हें पसंद है।
D: Send me a slow-motion video of you saying "come here". | "इधर आओ" बोलते हुए अपना स्लो-मोशन वीडियो भेजो।
TXT)],
    ['slug' => 'atod-bold', 'title' => 'Bold', 'title_hi' => 'बोल्ड', 'level' => 'bold', 'questions' => cc_tod(<<<'TXT'
T: Where would you love to be kissed that I haven't tried yet? | तुम्हें कहाँ किस किया जाना पसंद आएगा जो मैंने अब तक नहीं किया?
T: What's the most romantic place you've imagined us alone? | तुमने हमें अकेले सबसे रोमांटिक जगह कौन सी सोची है?
T: What do I wear that makes it hard to focus? | मैं क्या पहनता/पहनती हूँ जिससे तुम्हारा ध्यान भटक जाता है?
T: What's your favourite kind of kiss, slow or sudden? | तुम्हें कैसी किस पसंद है, धीमी या अचानक?
T: What's a romantic fantasy you've had about a holiday with me? | मेरे साथ छुट्टी को लेकर तुम्हारी कौन सी रोमांटिक फ़ैंटेसी है?
T: When did you last feel really attracted to me? | आख़िरी बार कब तुम्हें मेरी तरफ़ बहुत खिंचाव महसूस हुआ?
T: What do you want me to do more of when we're alone? | जब हम अकेले हों, तुम चाहते हो मैं क्या और ज़्यादा करूँ?
T: Which part of a date makes you most excited? | डेट का कौन सा हिस्सा तुम्हें सबसे ज़्यादा रोमांचित करता है?
T: What's the boldest text you've wanted to send me? | सबसे बोल्ड मैसेज कौन सा है जो तुम मुझे भेजना चाहते थे?
T: Would you rather be teased or be the one teasing? | तुम्हें छेड़ा जाना पसंद है या छेड़ना?
T: What's one thing about my touch you can't forget? | मेरे स्पर्श की कौन सी बात तुम भूल नहीं पाते?
T: What do you think about right before we meet? | मिलने से ठीक पहले तुम क्या सोचते हो?
D: Describe our next kiss in slow detail. | हमारी अगली किस को धीरे-धीरे, विस्तार से बताओ।
D: Send me a voice note of what you'd whisper in my ear. | वॉइस नोट में बताओ कि तुम मेरे कान में क्या फुसफुसाओगे।
D: Choose an outfit for me for our next date night and send it. | हमारी अगली डेट नाइट के लिए मेरी ड्रेस चुनकर भेजो।
D: Text me exactly how you'd greet me at the door tonight. | मैसेज करो कि आज रात दरवाज़े पर तुम मुझे कैसे मिलोगे।
D: Give me a one-minute shoulder massage, or promise one with a voice note. | मुझे एक मिनट कंधे की मालिश दो, या वॉइस नोट में उसका वादा करो।
D: Hold eye contact with me for 30 seconds without laughing. | बिना हँसे 30 सेकंड तक मेरी आँखों में देखो।
D: Send me a photo in the outfit you feel most confident in. | जिस ड्रेस में तुम सबसे कॉन्फ़िडेंट महसूस करते हो, उसमें फ़ोटो भेजो।
D: Tell me the moment you'd pause if our love story were a movie. | अगर हमारी लव स्टोरी फ़िल्म होती, तो तुम कौन सा सीन रोककर देखते?
D: Plan a surprise romantic evening and send me only one hint. | एक सरप्राइज़ रोमांटिक शाम प्लान करो और मुझे सिर्फ़ एक इशारा भेजो।
D: Kiss my hand slowly, or describe how you would. | मेरे हाथ को धीरे से चूमो, या बताओ कि कैसे चूमोगे।
D: Dance with me to one slow song, in person or on video. | मेरे साथ एक धीमे गाने पर डांस करो, सामने या वीडियो पर।
D: Send a voice note finishing this line: "Tonight I want…" | वॉइस नोट में यह लाइन पूरी करो: "आज रात मैं चाहता/चाहती हूँ…"
TXT)],
    ['slug' => 'atod-spicy', 'title' => 'Spicy', 'title_hi' => 'स्पाइसी', 'level' => 'spicy', 'questions' => cc_tod(<<<'TXT'
T: What's the most irresistible thing I do without trying? | मैं बिना कोशिश के सबसे क़ातिलाना चीज़ क्या करता/करती हूँ?
T: Which memory of us still gives you goosebumps? | हमारी कौन सी याद आज भी तुम्हें रोंगटे खड़े कर देती है?
T: What's one thing you want to try together, at your pace? | ऐसी कौन सी चीज़ है जो तुम अपनी रफ़्तार से साथ में ट्राई करना चाहोगे?
T: What makes you feel most wanted by me? | किस बात से तुम्हें सबसे ज़्यादा लगता है कि मैं तुम्हें चाहता/चाहती हूँ?
T: What is your favourite way to be held? | तुम्हें किस तरह बाहों में भरा जाना सबसे पसंद है?
T: If we had a whole night with no plans, how would you start it? | अगर हमारे पास बिना प्लान की पूरी रात हो, तो तुम कैसे शुरू करोगे?
T: What's a romantic scene from a film you'd love to recreate with me? | किसी फ़िल्म का कौन सा रोमांटिक सीन तुम मेरे साथ दोहराना चाहोगे?
T: What's your favourite place on me to rest your head? | मुझ पर सिर रखने की तुम्हारी पसंदीदा जगह कौन सी है?
T: Which of my scents drives you crazy? | मेरी कौन सी ख़ुशबू तुम्हें पागल कर देती है?
T: What's the sweetest thing you want to hear in the dark? | अँधेरे में तुम सबसे मीठी बात क्या सुनना चाहोगे?
D: Kiss me somewhere new tonight, or tell me exactly where you'd choose. | आज रात मुझे किसी नई जगह किस करो, या बताओ तुम कहाँ चुनोगे।
D: Give me a slow back massage for three minutes. | तीन मिनट तक धीरे-धीरे मेरी पीठ की मालिश करो।
D: Blindfold me with a scarf and feed me something sweet. | दुपट्टे से मेरी आँखें बाँधो और मुझे कुछ मीठा खिलाओ।
D: Send me a voice note describing our perfect night, slowly. | धीरे-धीरे हमारी परफ़ेक्ट रात के बारे में वॉइस नोट भेजो।
D: Trace a heart on my back with your finger. | अपनी उँगली से मेरी पीठ पर दिल बनाओ।
D: Leave a kiss mark on a note and give it to me. | एक नोट पर किस का निशान छोड़ो और मुझे दो।
D: Whisper something only you know about me. | मेरे बारे में कुछ ऐसा फुसफुसाओ जो सिर्फ़ तुम जानते हो।
D: Choose a song and slow dance as close as possible. | एक गाना चुनो और जितना हो सके पास रहकर धीमा डांस करो।
D: Kiss my neck once, or describe it in a voice note. | मेरी गर्दन पर एक बार किस करो, या वॉइस नोट में बताओ।
D: Write "tonight" on a note, fold it, and hand it to me with a wink. | एक नोट पर "आज रात" लिखो, मोड़ो और आँख मारते हुए मुझे दो।
D: Hold me from behind for one full song. | एक पूरे गाने तक मुझे पीछे से पकड़कर रखो।
D: Tell me your favourite memory of us alone, in three slow sentences. | हमारे अकेले पलों की अपनी पसंदीदा याद तीन धीमे वाक्यों में बताओ।
TXT)],
    ['slug' => 'atod-intimate', 'title' => 'Intimate', 'title_hi' => 'इंटिमेट', 'level' => 'intimate', 'questions' => cc_tod(<<<'TXT'
T: When do you feel closest to me without saying a word? | बिना कुछ बोले तुम मुझसे सबसे ज़्यादा क़रीब कब महसूस करते हो?
T: What helps you feel safe enough to be completely yourself with me? | किस चीज़ से तुम मेरे साथ पूरी तरह ख़ुद होने में सुरक्षित महसूस करते हो?
T: What's one thing you'd like us to slow down and enjoy more? | ऐसी कौन सी चीज़ है जिसे हमें धीरे-धीरे और ज़्यादा जीना चाहिए?
T: What's the most tender moment we've shared? | हमारा सबसे कोमल पल कौन सा था?
T: How do you like to be touched when you're stressed? | तनाव में होने पर तुम्हें कैसा स्पर्श पसंद है?
T: What's a boundary of yours you'd like me to always remember? | तुम्हारी कौन सी सीमा है जो तुम चाहते हो मैं हमेशा याद रखूँ?
T: What makes you feel beautiful around me? | मेरे साथ तुम्हें किस बात से ख़ूबसूरत महसूस होता है?
T: Which moment of closeness do you replay in your mind? | नज़दीकी का कौन सा पल तुम मन में बार-बार दोहराते हो?
D: Look into my eyes and tell me what you love about me, slowly. | मेरी आँखों में देखकर धीरे-धीरे बताओ तुम्हें मुझमें क्या पसंद है।
D: Hold my hands and breathe together for one minute. | मेरे हाथ पकड़ो और एक मिनट साथ में साँस लो।
D: Give me a forehead kiss and a long hug. | मेरे माथे पर किस करो और एक लंबी झप्पी दो।
D: Lie close and share one dream for our future. | पास लेटो और हमारे भविष्य का एक सपना बताओ।
D: Brush my hair gently for two minutes. | दो मिनट तक धीरे से मेरे बाल सहलाओ।
D: Write three things you want to give me, not things you can buy. | तीन चीज़ें लिखो जो तुम मुझे देना चाहते हो, जो ख़रीदी न जा सकें।
D: Put on soft music and just hold each other, no talking. | धीमा म्यूज़िक लगाओ और बिना बोले एक-दूसरे को थामे रहो।
D: Tell me what you need from me tonight, honestly and kindly. | ईमानदारी और प्यार से बताओ कि आज रात तुम्हें मुझसे क्या चाहिए।
TXT)],
  ]],

  ['game' => 'intimacy-question-cards', 'packs' => [
    ['slug' => 'iqc-romantic', 'title' => 'Romantic', 'title_hi' => 'रोमांटिक', 'level' => 'romantic', 'questions' => cc_cards('question', <<<'TXT'
What does a perfect cuddle feel like to you? | तुम्हारे लिए परफ़ेक्ट लिपटना कैसा होता है?
Which of my gestures makes you feel desired? | मेरा कौन सा इशारा तुम्हें चाहे जाने का एहसास दिलाता है?
What was our most romantic evening so far? | अब तक हमारी सबसे रोमांटिक शाम कौन सी थी?
What kind of kiss melts you instantly? | कैसी किस तुम्हें तुरंत पिघला देती है?
How do you like to be woken up by me? | तुम्हें मुझसे कैसे जगाया जाना पसंद है?
What is your favourite way to spend a rainy night with me? | मेरे साथ बारिश की रात बिताने का तुम्हारा पसंदीदा तरीक़ा क्या है?
Which song would you want playing on our most romantic night? | हमारी सबसे रोमांटिक रात में तुम कौन सा गाना चलाना चाहोगे?
What do you find most attractive about my personality? | मेरी पर्सनैलिटी में तुम्हें सबसे आकर्षक क्या लगता है?
What makes you feel closest to me after a fight? | झगड़े के बाद किस बात से तुम मुझसे सबसे क़रीब महसूस करते हो?
Where would you love a surprise kiss? | तुम्हें कहाँ अचानक किस पसंद आएगी?
What's a small touch that means a lot to you? | कौन सा छोटा सा स्पर्श तुम्हारे लिए बहुत मायने रखता है?
What's the most romantic thing you want to do on our next trip? | हमारी अगली ट्रिप पर तुम सबसे रोमांटिक क्या करना चाहोगे?
How do you like me to show I missed you? | तुम्हें कैसे पसंद है कि मैं जताऊँ कि मुझे तुम्हारी याद आई?
What do you love about our first few minutes together after time apart? | दूर रहने के बाद मिलने के पहले कुछ मिनटों में तुम्हें क्या पसंद है?
What makes a night "special" for you? | तुम्हारे लिए कौन सी बात एक रात को "ख़ास" बनाती है?
What's one romantic habit you'd like us to start? | ऐसी कौन सी रोमांटिक आदत है जो हम शुरू करें?
What's the most attractive thing I've ever said to you? | मैंने तुमसे सबसे आकर्षक बात क्या कही है?
How do you want to feel when you fall asleep next to me? | मेरे पास सोते समय तुम कैसा महसूस करना चाहते हो?
What do you love most about holding hands with me? | मेरा हाथ पकड़ने की कौन सी बात तुम्हें सबसे ज़्यादा पसंद है?
What would you whisper to me on our wedding night? | हमारी शादी की रात तुम मुझसे क्या फुसफुसाओगे?
TXT)],
    ['slug' => 'iqc-intimate', 'title' => 'Intimate', 'title_hi' => 'इंटिमेट', 'level' => 'intimate', 'questions' => cc_cards('question', <<<'TXT'
What helps you get in the mood after a long day? | लंबे दिन के बाद तुम्हें मूड में आने में क्या मदद करता है?
What's something you'd love me to take the lead on? | तुम चाहोगे किस चीज़ में मैं पहल करूँ?
How do you like to be asked before trying something new? | कुछ नया ट्राई करने से पहले तुम कैसे पूछा जाना पसंद करते हो?
What is one way I can make you feel more relaxed with me? | मैं तुम्हें अपने साथ और सहज कैसे महसूस करा सकता/सकती हूँ?
Which time of day do you feel most romantic? | दिन के किस समय तुम सबसे रोमांटिक महसूस करते हो?
What kind of words make you feel most desired? | कैसे शब्द तुम्हें सबसे ज़्यादा चाहे जाने का एहसास दिलाते हैं?
Is there something you've been shy to ask for? | क्या कुछ ऐसा है जो माँगने में तुम शरमाते रहे?
What does feeling safe in intimacy mean to you? | नज़दीकी में सुरक्षित महसूस करने का तुम्हारे लिए क्या मतलब है?
Do you prefer slow and gentle or playful and fun? | तुम्हें धीमा और कोमल पसंद है या मस्ती भरा?
What's a setting that would make you feel most comfortable? | किस माहौल में तुम सबसे ज़्यादा सहज महसूस करोगे?
How can we keep the spark alive when life gets busy? | ज़िंदगी व्यस्त हो जाए तब भी हम चिंगारी कैसे बनाए रखें?
What's your favourite part of the moments after we're close? | क़रीब आने के बाद वाले पलों में तुम्हें सबसे अच्छा क्या लगता है?
What's one thing you'd like to try that feels new but safe? | ऐसी कौन सी चीज़ है जो नई हो पर सुरक्षित लगे?
How do you want me to check in with you? | तुम चाहते हो मैं तुम्हारा हाल कैसे पूछूँ?
What would a perfect "us" night look like, start to finish? | शुरू से आख़िर तक, "हमारी" एक परफ़ेक्ट रात कैसी होगी?
What makes you feel confident in your own body? | किस बात से तुम अपनी बॉडी में कॉन्फ़िडेंट महसूस करते हो?
TXT)],
  ]],

  ['game' => 'spicy-would-you-rather', 'packs' => [
    ['slug' => 'swyr-bold', 'title' => 'Bold', 'title_hi' => 'बोल्ड', 'level' => 'bold', 'questions' => cc_cards('question', <<<'TXT'
Would you rather… | तुम क्या चुनोगे… | Kiss in the rain; Kiss under the stars | बारिश में किस; तारों के नीचे किस
Would you rather… | तुम क्या चुनोगे… | A slow kiss; A playful kiss | धीमी किस; शरारती किस
Would you rather… | तुम क्या चुनोगे… | A candlelit bath; A midnight swim | मोमबत्तियों वाला बाथ; आधी रात की तैराकी
Would you rather… | तुम क्या चुनोगे… | Be blindfolded; Do the blindfolding | आँखों पर पट्टी बँधवाना; पट्टी बाँधना
Would you rather… | तुम क्या चुनोगे… | Morning cuddles; Late-night cuddles | सुबह का लिपटना; देर रात का लिपटना
Would you rather… | तुम क्या चुनोगे… | Receive a flirty voice note; Receive a flirty letter | फ़्लर्टी वॉइस नोट पाना; फ़्लर्टी चिट्ठी पाना
Would you rather… | तुम क्या चुनोगे… | A weekend in a hill cottage; A weekend in a beach villa | पहाड़ी कॉटेज में वीकेंड; बीच विला में वीकेंड
Would you rather… | तुम क्या चुनोगे… | Be teased all day by text; Be surprised at night | पूरा दिन मैसेज पर छेड़ा जाना; रात में सरप्राइज़
Would you rather… | तुम क्या चुनोगे… | Dress up for each other; Stay in comfy clothes together | एक-दूसरे के लिए सजना; साथ में आरामदायक कपड़ों में रहना
Would you rather… | तुम क्या चुनोगे… | A full-body massage; A long head massage | पूरे शरीर की मालिश; सिर की लंबी मालिश
Would you rather… | तुम क्या चुनोगे… | Make the first move; Wait to be pursued | पहल करना; पहल का इंतज़ार
Would you rather… | तुम क्या चुनोगे… | A secret kiss at a party; A long kiss at home | पार्टी में चुपके से किस; घर पर लंबी किस
Would you rather… | तुम क्या चुनोगे… | Soft music; Complete silence | धीमा म्यूज़िक; पूरी ख़ामोशी
Would you rather… | तुम क्या चुनोगे… | Candlelight; Fairy lights | मोमबत्ती की रोशनी; फ़ेयरी लाइट्स
Would you rather… | तुम क्या चुनोगे… | Breakfast in bed; Dessert in bed | बिस्तर पर नाश्ता; बिस्तर पर डेज़र्ट
Would you rather… | तुम क्या चुनोगे… | A slow dance in the kitchen; A slow dance on the terrace | रसोई में धीमा डांस; छत पर धीमा डांस
Would you rather… | तुम क्या चुनोगे… | Be whispered to; Be the one whispering | फुसफुसाकर सुनना; फुसफुसाना
Would you rather… | तुम क्या चुनोगे… | A forehead kiss; A neck kiss | माथे पर किस; गर्दन पर किस
Would you rather… | तुम क्या चुनोगे… | Hold hands all evening; Hug all evening | पूरी शाम हाथ पकड़ना; पूरी शाम गले लगना
Would you rather… | तुम क्या चुनोगे… | A rooftop dinner; A private picnic | छत पर डिनर; प्राइवेट पिकनिक
TXT)],
    ['slug' => 'swyr-spicy', 'title' => 'Spicy', 'title_hi' => 'स्पाइसी', 'level' => 'spicy', 'questions' => cc_cards('question', <<<'TXT'
Would you rather… | तुम क्या चुनोगे… | Be seduced slowly; Be swept away suddenly | धीरे-धीरे लुभाया जाना; अचानक बहा ले जाया जाना
Would you rather… | तुम क्या चुनोगे… | Lingerie or silk night wear surprise; A matching couple outfit | लॉन्जरी या सिल्क नाइटवियर सरप्राइज़; मैचिंग कपल आउटफ़िट
Would you rather… | तुम क्या चुनोगे… | A hotel night in the city; A tent under the stars | शहर के होटल में एक रात; तारों के नीचे टेंट
Would you rather… | तुम क्या चुनोगे… | Ice cube tease; Warm oil massage | बर्फ़ के टुकड़े से छेड़ना; गुनगुने तेल की मालिश
Would you rather… | तुम क्या चुनोगे… | Take the lead tonight; Be led tonight | आज रात तुम पहल करो; आज रात मैं करूँ
Would you rather… | तुम क्या चुनोगे… | A bubble bath for two; A shower for two | दो लोगों का बबल बाथ; दो लोगों का शावर
Would you rather… | तुम क्या चुनोगे… | Strawberries and chocolate; Wine and cheese | स्ट्रॉबेरी और चॉकलेट; वाइन और चीज़
Would you rather… | तुम क्या चुनोगे… | A slow undressing game; A kissing-only rule for 10 minutes | धीरे-धीरे वाला खेल; 10 मिनट सिर्फ़ किस का नियम
Would you rather… | तुम क्या चुनोगे… | Be teased with feathers; Be teased with kisses | पंख से छेड़ा जाना; किस से छेड़ा जाना
Would you rather… | तुम क्या चुनोगे… | Mirror selfie together; No photos, just memories | साथ में मिरर सेल्फ़ी; कोई फ़ोटो नहीं, बस यादें
Would you rather… | तुम क्या चुनोगे… | Morning romance; Midnight romance | सुबह का रोमांस; आधी रात का रोमांस
Would you rather… | तुम क्या चुनोगे… | Role-play strangers at a bar; Role-play honeymooners | बार में अजनबी बनना; हनीमून वाला कपल बनना
Would you rather… | तुम क्या चुनोगे… | A love note on the mirror; A love note in my pocket | आईने पर लव नोट; मेरी जेब में लव नोट
Would you rather… | तुम क्या चुनोगे… | Dim lights; Candles only | मद्धम रोशनी; सिर्फ़ मोमबत्तियाँ
Would you rather… | तुम क्या चुनोगे… | A surprise weekend away; A surprise night in | सरप्राइज़ वीकेंड बाहर; सरप्राइज़ रात घर पर
Would you rather… | तुम क्या चुनोगे… | Get a sensual massage; Give a sensual massage | रोमांटिक मालिश लेना; रोमांटिक मालिश देना
Would you rather… | तुम क्या चुनोगे… | A flirty dare; A deep truth | एक फ़्लर्टी डेयर; एक गहरा सच
Would you rather… | तुम क्या चुनोगे… | Be kissed everywhere but the lips; Only on the lips for an hour | होंठों के अलावा हर जगह किस; एक घंटे सिर्फ़ होंठों पर
Would you rather… | तुम क्या चुनोगे… | A shared bath robe morning; A shared blanket night | साथ में बाथरोब वाली सुबह; एक कंबल वाली रात
Would you rather… | तुम क्या चुनोगे… | Hear my fantasies; Tell me yours | मेरी फ़ैंटेसी सुनना; अपनी बताना
TXT)],
  ]],

  ['game' => 'flirty-conversation-starters', 'packs' => [
    ['slug' => 'fcs', 'title' => 'Flirty', 'level' => 'flirty', 'questions' => cc_cards('question', <<<'TXT'
What's the first thing you'd do if I showed up at your door right now? | अगर मैं अभी तुम्हारे दरवाज़े पर आ जाऊँ, तो तुम सबसे पहले क्या करोगे?
What's one thing about me that's impossible to resist? | मेरी कौन सी बात से बचना नामुमकिन है?
Describe your perfect kiss in five words. | पाँच शब्दों में अपनी परफ़ेक्ट किस बताओ।
What are you wearing right now, and what would I think of it? | अभी तुमने क्या पहना है, और मैं उसके बारे में क्या सोचूँगा/सोचूँगी?
What would our first five minutes alone tonight look like? | आज रात अकेले में हमारे पहले पाँच मिनट कैसे होंगे?
What's a text from me that made your heart race? | मेरा कौन सा मैसेज पढ़कर तुम्हारी धड़कन बढ़ गई थी?
What do you want to hear me say right now? | तुम अभी मुझसे क्या सुनना चाहते हो?
Which part of our last date do you keep thinking about? | हमारी पिछली डेट का कौन सा हिस्सा तुम सोचते रहते हो?
What's your favourite thing to do with my hands? | मेरे हाथों के साथ तुम्हें क्या करना सबसे अच्छा लगता है?
If you could steal one kiss from me today, when would it be? | अगर आज तुम मुझसे एक किस चुरा सको, तो कब चुराओगे?
What emoji would you send if you could only flirt in emojis? | अगर सिर्फ़ इमोजी में फ़्लर्ट कर सको, तो कौन सा भेजोगे?
What's something I do that you secretly find hot? | मेरी कौन सी आदत तुम्हें चुपके से हॉट लगती है?
What should I wear on our next video call? | अगली वीडियो कॉल पर मुझे क्या पहनना चाहिए?
Tell me a dream you had about me (keep it sweet). | मेरे बारे में देखा कोई सपना बताओ (प्यारा सा रखना)।
What's the best way to say good night to you? | तुम्हें गुड नाइट कहने का सबसे अच्छा तरीक़ा क्या है?
What's one word that describes how you feel about me tonight? | एक शब्द में बताओ आज रात तुम मेरे लिए क्या महसूस कर रहे हो।
What would you do if we were stuck in a lift together? | अगर हम साथ में लिफ़्ट में फँस जाएँ तो तुम क्या करोगे?
What is your favourite memory of my lips? | मेरे होंठों की तुम्हारी पसंदीदा याद कौन सी है?
Which compliment from me would make you blush the most? | मेरी कौन सी तारीफ़ तुम्हें सबसे ज़्यादा शरमा देगी?
What would you write on a sticky note on my mirror? | मेरे आईने पर चिपकाने वाले नोट पर तुम क्या लिखोगे?
Where do you want me to kiss you first when we meet? | मिलने पर तुम चाहते हो मैं तुम्हें पहली किस कहाँ करूँ?
What's your favourite thing about the way I look at you? | मेरे तुम्हें देखने के अंदाज़ की कौन सी बात तुम्हें पसंद है?
Which outfit of mine should be banned for being too distracting? | मेरी कौन सी ड्रेस इतनी ध्यान भटकाती है कि उस पर बैन लगना चाहिए?
If tonight had a title, what would it be? | अगर आज की रात का कोई नाम होता, तो क्या होता?
What would make you text me "come over" right now? | किस बात पर तुम मुझे अभी "आ जाओ" मैसेज करोगे?
What do you think I'm thinking about right now? | तुम्हें क्या लगता है मैं अभी क्या सोच रहा/रही हूँ?
What's a flirty nickname you'd give me? | मुझे तुम कौन सा फ़्लर्टी निकनेम दोगे?
Which song should be playing when we see each other next? | अगली बार मिलते वक़्त कौन सा गाना बजना चाहिए?
What would you do with ten uninterrupted minutes with me? | मेरे साथ बिना रुकावट के दस मिनट मिलें तो क्या करोगे?
What's the most romantic thing you want to whisper to me? | सबसे रोमांटिक बात कौन सी है जो तुम मुझसे फुसफुसाना चाहोगे?
TXT)],
  ]],
];
