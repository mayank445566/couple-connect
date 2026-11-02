<?php
require_once __DIR__ . '/_helpers.php';

$love = ['Love it', 'Not for me'];
$loveHi = ['बहुत पसंद', 'मेरे लिए नहीं'];

return [
  ['game' => 'daily-couple-challenge', 'packs' => [
    ['slug' => 'dcc', 'title' => 'Daily', 'level' => 'easy', 'questions' => cc_cards('challenge', <<<'TXT'
Send each other a voice note saying one thing you love about today. | एक-दूसरे को वॉइस नोट भेजो और बताओ आज की कौन सी बात अच्छी लगी।
Take a photo of your lunch and guess what the other is eating. | अपने लंच की फ़ोटो लो और अंदाज़ा लगाओ कि दूसरा क्या खा रहा है।
Compliment each other's style today. | आज एक-दूसरे के स्टाइल की तारीफ़ करो।
Text each other a memory from the first month together. | साथ के पहले महीने की कोई याद एक-दूसरे को मैसेज करो।
Drink a glass of water together on video. Cheers to health! | वीडियो पर साथ में एक गिलास पानी पियो। सेहत के नाम!
Share your screen time and pick one app to use less. | अपना स्क्रीन टाइम शेयर करो और एक ऐप चुनो जिसे कम चलाना है।
Do 10 squats each and send proof. | दस-दस स्क्वैट्स करो और सबूत भेजो।
Write a six-word story about us. | हमारे बारे में छह शब्दों की एक कहानी लिखो।
Plan tomorrow's dinner together. | कल का डिनर साथ में प्लान करो।
Say "thank you" for one specific thing from this week. | इस हफ़्ते की किसी ख़ास बात के लिए "शुक्रिया" कहो।
Send a song for each other's mood right now. | एक-दूसरे के अभी के मूड के लिए एक गाना भेजो।
Look up at the sky and send a photo of it. | आसमान की तरफ़ देखो और उसकी फ़ोटो भेजो।
Go to bed at the same time tonight. | आज रात एक ही समय पर सोने जाओ।
No phones for the first 20 minutes of your call. | कॉल के पहले 20 मिनट फ़ोन पर कुछ और मत चलाओ।
Name three things you're looking forward to this month. | इस महीने की तीन चीज़ें बताओ जिनका तुम्हें इंतज़ार है।
Recreate an old photo of yourself and send both. | अपनी एक पुरानी फ़ोटो दोबारा खींचो और दोनों भेजो।
Teach each other one new word in any language. | एक-दूसरे को किसी भी भाषा का एक नया शब्द सिखाओ।
Share a childhood photo the other has never seen. | बचपन की ऐसी फ़ोटो शेयर करो जो दूसरे ने कभी नहीं देखी।
Write each other a two-line good-night note. | एक-दूसरे को दो लाइन का गुड नाइट नोट लिखो।
Make a playlist of five songs for your next date. | अगली डेट के लिए पाँच गानों की प्लेलिस्ट बनाओ।
Tell each other one thing you admire about the other's family. | एक-दूसरे के परिवार की एक अच्छी बात बताओ।
Send a selfie with your current mood, no filters. | बिना फ़िल्टर के अपने अभी के मूड वाली सेल्फ़ी भेजो।
Pick a dream holiday destination together. | साथ में एक ड्रीम हॉलिडे की जगह चुनो।
Do a one-minute breathing exercise together on a call. | कॉल पर साथ में एक मिनट साँस वाली एक्सरसाइज़ करो।
Agree on one small habit to build together this week. | इस हफ़्ते साथ में अपनाने के लिए एक छोटी आदत तय करो।
Text "I'm proud of you" with a reason. | वजह के साथ "मुझे तुम पर गर्व है" मैसेज करो।
Swap your favourite reels or videos of the day. | आज की अपनी पसंदीदा रील या वीडियो एक-दूसरे को भेजो।
Make each other laugh with the worst joke you know. | अपने सबसे बेकार जोक से एक-दूसरे को हँसाओ।
Write down one goal for the year and share it. | साल का एक लक्ष्य लिखो और शेयर करो।
Send a photo of something that is the other's favourite colour. | दूसरे के पसंदीदा रंग वाली किसी चीज़ की फ़ोटो भेजो।
TXT)],
  ]],

  ['game' => 'guess-the-memory', 'packs' => [
    ['slug' => 'gtm', 'title' => 'Clues', 'level' => 'medium', 'questions' => cc_cards('question', <<<'TXT'
Describe a memory using only three emojis. | सिर्फ़ तीन इमोजी से कोई याद बताओ।
Name the weather on a day we'll never forget. | उस दिन का मौसम बताओ जिसे हम कभी नहीं भूलेंगे।
Give the name of a shop or café connected to a memory. | किसी याद से जुड़ी दुकान या कैफ़े का नाम बताओ।
Describe a moment using one colour. | एक रंग से कोई पल बताओ।
Name a gift and let me guess the occasion. | कोई तोहफ़ा बताओ और मुझे मौक़ा पहचानने दो।
Quote something one of us said and let me guess when. | हम में से किसी की कही बात दोहराओ और मुझे बताने दो कि कब कही थी।
Name a time of day that brings back a special memory. | दिन का कोई समय बताओ जो कोई ख़ास याद दिलाए।
Describe a trip using only the transport we took. | सिर्फ़ जिस सवारी से गए थे, उससे कोई ट्रिप बताओ।
TXT)],
  ]],

  ['game' => 'two-truths-and-a-lie', 'packs' => [
    ['slug' => 'ttal', 'title' => 'About Me', 'level' => 'easy', 'questions' => cc_cards('two_truths', <<<'TXT'
Three statements about your first job or internship. | अपनी पहली नौकरी या इंटर्नशिप के बारे में तीन बातें।
Three statements about your friends. | अपने दोस्तों के बारे में तीन बातें।
Three statements about places you have travelled. | जिन जगहों पर तुम घूमे हो, उनके बारे में तीन बातें।
Three statements about your family. | अपने परिवार के बारे में तीन बातें।
Three statements about your fears. | अपने डर के बारे में तीन बातें।
Three statements about your first impression of me. | मेरे बारे में तुम्हारी पहली राय पर तीन बातें।
Three statements about your favourite foods. | अपने पसंदीदा खाने के बारे में तीन बातें।
Three statements about embarrassing moments. | शर्मिंदगी वाले पलों के बारे में तीन बातें।
Three statements about your hobbies. | अपने शौक़ के बारे में तीन बातें।
Three statements about what you did last weekend. | पिछले वीकेंड तुमने क्या किया, उस पर तीन बातें।
TXT)],
  ]],

  ['game' => 'compatibility-quiz', 'packs' => [
    ['slug' => 'cq-more', 'title' => 'Life Together', 'title_hi' => 'साथ की ज़िंदगी', 'level' => 'medium', 'questions' => cc_cards('question', <<<'TXT'
Living with parents after marriage? | शादी के बाद माता-पिता के साथ रहना? | Yes, joint family; Nearby but separate; Separate city is fine | हाँ, संयुक्त परिवार; पास में पर अलग; अलग शहर भी ठीक
Who manages money? | पैसे कौन संभालेगा? | One of us; Both together; Separate accounts | हम में से एक; दोनों साथ; अलग-अलग अकाउंट
Ideal wedding budget? | शादी का आइडियल बजट? | Simple; Medium; Grand | सादा; मीडियम; शानदार
How often should we go on dates? | हमें कितनी बार डेट पर जाना चाहिए? | Every week; Twice a month; Once a month | हर हफ़्ते; महीने में दो बार; महीने में एक बार
Friends over at home? | घर पर दोस्त? | Often; Sometimes; Rarely | अक्सर; कभी-कभी; बहुत कम
Career or family first? | पहले करियर या परिवार? | Career; Family; Balance both | करियर; परिवार; दोनों में संतुलन
Phone during dinner? | डिनर पर फ़ोन? | Never; Only if urgent; It's fine | कभी नहीं; सिर्फ़ ज़रूरी हो तो; चलता है
Sharing passwords? | पासवर्ड शेयर करना? | Yes, everything; Some; No, privacy matters | हाँ, सब; कुछ; नहीं, प्राइवेसी ज़रूरी है
Festival plans? | त्योहार कैसे? | With my family; With your family; Alternate each year; Just us | मेरे परिवार के साथ; तुम्हारे परिवार के साथ; हर साल बदल-बदल कर; सिर्फ़ हम
Morning routine? | सुबह का रूटीन? | Wake early together; Sleep in; Different times is fine | साथ जल्दी उठना; देर तक सोना; अलग समय भी ठीक
Vacation frequency? | छुट्टियाँ कितनी बार? | Every few months; Once a year; When we can | हर कुछ महीने; साल में एक बार; जब हो सके
Gift style? | तोहफ़ों का अंदाज़? | Expensive and rare; Small and often; Experiences, not things | महँगे और कभी-कभी; छोटे और अक्सर; चीज़ें नहीं, अनुभव
Social media couple posts? | सोशल मीडिया पर कपल पोस्ट? | Love them; Sometimes; Keep it private | बहुत पसंद; कभी-कभी; प्राइवेट रखो
How to spend a bonus? | बोनस कैसे ख़र्च करें? | Save it; Travel; Treat the family | बचाओ; घूमो; परिवार को ट्रीट
Cooking at home? | घर पर खाना? | Mostly home-cooked; Half and half; Mostly order in | ज़्यादातर घर का; आधा-आधा; ज़्यादातर ऑर्डर
Night routine? | रात का रूटीन? | Talk before sleep; Watch something; Read quietly | सोने से पहले बातें; कुछ देखना; चुपचाप पढ़ना
Big decisions? | बड़े फ़ैसले? | Always together; Whoever knows more; Ask family too | हमेशा साथ; जिसे ज़्यादा पता हो; परिवार से भी पूछें
Space when upset? | नाराज़गी में स्पेस? | Need space; Need closeness; Depends | स्पेस चाहिए; पास रहना चाहिए; हालात पर निर्भर
Dream home? | ड्रीम घर? | City apartment; House with garden; Farmhouse | शहर का फ़्लैट; बग़ीचे वाला घर; फ़ार्महाउस
Weekend mornings? | वीकेंड की सुबह? | Brunch out; Lazy at home; Sports or gym | बाहर ब्रंच; घर पर आराम; खेल या जिम
TXT)],
  ]],

  ['game' => 'date-night-ideas', 'packs' => [
    ['slug' => 'dni-more', 'title' => 'More Ideas', 'title_hi' => 'और आइडिया', 'level' => 'easy', 'questions' => cc_cards('question', <<<'TXT'
Sunrise trek | सूर्योदय ट्रेक
Bowling night | बॉलिंग नाइट
Cook each other's favourite dish | एक-दूसरे की पसंदीदा डिश बनाना
Visit a book café | बुक कैफ़े जाना
Paint each other's portraits | एक-दूसरे की तस्वीर बनाना
Go-karting | गो-कार्टिंग
Old-songs antakshari night | पुराने गानों की अंताक्षरी
Dessert crawl in the city | शहर में मिठाइयों की सैर
Spa day at home | घर पर स्पा डे
Plant a tree together | साथ में पेड़ लगाना
Watch the stars from the terrace | छत से तारे देखना
Build a blanket fort | कंबलों का क़िला बनाना
Photography walk | फ़ोटोग्राफ़ी वॉक
Try a dance class together | साथ में डांस क्लास
Make a scrapbook of us | हमारी स्क्रैपबुक बनाना
Midnight Maggi and a movie | आधी रात मैगी और फ़िल्म
Visit a temple, gurudwara or church at dawn | सुबह-सुबह मंदिर, गुरुद्वारा या चर्च जाना
Write future letters to each other | एक-दूसरे को भविष्य के लिए चिट्ठियाँ लिखना
Play video games together | साथ में वीडियो गेम खेलना
Try a new cuisine | कोई नया खाना ट्राई करना
Puzzle night with snacks | स्नैक्स के साथ पज़ल नाइट
Visit a local fair or mela | किसी मेले में जाना
Boat ride | नाव की सवारी
Volunteer together for a day | एक दिन साथ में सेवा करना
Recreate our first date | अपनी पहली डेट दोबारा करना
Picnic in the park with board games | पार्क में पिकनिक और बोर्ड गेम्स
Learn a few words of a new language | नई भाषा के कुछ शब्द सीखना
Long drive with no destination | बिना मंज़िल की लंबी ड्राइव
Try a cooking challenge with a mystery ingredient | सीक्रेट चीज़ के साथ कुकिंग चैलेंज
Watch the city from a rooftop café | रूफ़टॉप कैफ़े से शहर देखना
TXT, $love, $loveHi)],
  ]],

  ['game' => 'random-questions', 'packs' => [
    ['slug' => 'rqg-more', 'title' => 'Surprise Me', 'title_hi' => 'चौंकाओ मुझे', 'level' => 'easy', 'questions' => cc_cards('question', <<<'TXT'
What would you do with a free ticket anywhere? | कहीं का भी फ़्री टिकट मिले तो क्या करोगे?
Which cartoon character are you most like? | तुम किस कार्टून कैरेक्टर जैसे हो?
What is the weirdest food combination you love? | खाने का कौन सा अजीब कॉम्बिनेशन तुम्हें पसंद है?
What would your autobiography be called? | तुम्हारी आत्मकथा का नाम क्या होगा?
If you were a dessert, which would you be? | अगर तुम मिठाई होते, तो कौन सी होते?
What is the most useless talent you have? | तुम्हारा सबसे बेकार टैलेंट क्या है?
Which decade would you love to live in? | तुम किस दशक में जीना चाहोगे?
What would you name a boat? | तुम एक नाव का नाम क्या रखोगे?
What is your favourite sound? | तुम्हारी पसंदीदा आवाज़ कौन सी है?
What is one thing everyone should try once? | ऐसी कौन सी चीज़ है जो हर किसी को एक बार ज़रूर करनी चाहिए?
Which animal would you be for a day? | एक दिन के लिए तुम कौन सा जानवर बनना चाहोगे?
What's the best compliment a stranger ever gave you? | किसी अनजान से मिली सबसे अच्छी तारीफ़ कौन सी थी?
What's your favourite festival food? | त्योहार का तुम्हारा पसंदीदा खाना कौन सा है?
What would you do if you were invisible for a day? | एक दिन के लिए अदृश्य हो जाओ तो क्या करोगे?
What is your most-watched movie? | तुमने कौन सी फ़िल्म सबसे ज़्यादा बार देखी है?
Which board game makes you competitive? | कौन सा बोर्ड गेम तुम्हें बहुत कॉम्पिटिटिव बना देता है?
What is a smell you love that others hate? | ऐसी ख़ुशबू जो तुम्हें पसंद है पर दूसरों को नहीं?
What's the best thing you bought under ₹500? | ₹500 से कम में ख़रीदी सबसे अच्छी चीज़ क्या है?
What's your hidden phone wallpaper story? | तुम्हारे फ़ोन वॉलपेपर के पीछे की कहानी क्या है?
If you opened a café, what would it be called? | अगर तुम कैफ़े खोलो, तो उसका नाम क्या होगा?
What's your most-used sticker or GIF? | तुम्हारा सबसे ज़्यादा भेजा जाने वाला स्टिकर या GIF कौन सा है?
What's the strangest dream you remember? | तुम्हें याद आने वाला सबसे अजीब सपना कौन सा है?
Which song do you know every word of? | तुम्हें किस गाने का हर शब्द याद है?
What's the best advice you'd give your younger self? | अपने छोटे वाले ख़ुद को तुम क्या सलाह दोगे?
What would your superhero name be? | तुम्हारा सुपरहीरो नाम क्या होगा?
What is your favourite way to waste time? | समय बर्बाद करने का तुम्हारा पसंदीदा तरीक़ा क्या है?
Which famous person would you want to have chai with? | किस मशहूर इंसान के साथ चाय पीना चाहोगे?
What's a rule you always break? | कौन सा नियम तुम हमेशा तोड़ते हो?
What's one thing you can't live without? | ऐसी कौन सी चीज़ है जिसके बिना तुम नहीं रह सकते?
What would you do with ₹1 crore? | ₹1 करोड़ मिलें तो क्या करोगे?
What's the funniest thing that happened to you in school? | स्कूल में तुम्हारे साथ हुई सबसे मज़ेदार बात कौन सी है?
What's your favourite thing about where you grew up? | जहाँ तुम बड़े हुए, उसकी सबसे अच्छी बात क्या है?
Which fictional couple are we most like? | हम किस फ़िल्मी या किताबी जोड़ी जैसे हैं?
What is one thing you'd invent? | तुम कौन सी चीज़ ईजाद करना चाहोगे?
What do you always forget? | तुम हमेशा क्या भूल जाते हो?
What's your go-to dance move? | तुम्हारा पसंदीदा डांस स्टेप कौन सा है?
What's the most spontaneous thing you've done? | तुमने बिना सोचे-समझे सबसे बड़ा काम क्या किया?
If you had a theme song, what would it be? | अगर तुम्हारा एक थीम सॉन्ग होता, तो कौन सा होता?
What is the best rainy-day snack? | बारिश के दिन का सबसे अच्छा स्नैक कौन सा है?
What's a skill you'd love me to teach you? | ऐसा कौन सा हुनर है जो तुम चाहोगे मैं तुम्हें सिखाऊँ?
What's your favourite childhood game? | तुम्हारा बचपन का पसंदीदा खेल कौन सा था?
What would you put on a billboard? | तुम किसी बिलबोर्ड पर क्या लिखोगे?
Which app would you delete forever? | कौन सा ऐप तुम हमेशा के लिए डिलीट कर दोगे?
What's your favourite word in Hindi? | हिंदी में तुम्हारा पसंदीदा शब्द कौन सा है?
What makes a person instantly likeable? | किस बात से कोई इंसान तुरंत अच्छा लगने लगता है?
What would be your perfect last meal? | तुम्हारा परफ़ेक्ट आख़िरी खाना क्या होगा?
What is the bravest thing you've ever done? | तुमने अब तक का सबसे बहादुरी वाला काम क्या किया है?
Which place in the world feels magical to you? | दुनिया की कौन सी जगह तुम्हें जादुई लगती है?
What's a small thing that annoys you a lot? | कौन सी छोटी सी बात तुम्हें बहुत चिढ़ाती है?
What do you want to be remembered for? | तुम किस बात के लिए याद किए जाना चाहोगे?
TXT)],
  ]],

  ['game' => 'daily-relationship-questions', 'packs' => [
    ['slug' => 'daily-questions', 'title' => 'Daily', 'level' => 'easy', 'questions' => cc_cards('question', <<<'TXT'
What is one thing you want to tell me today? | आज तुम मुझसे एक बात क्या कहना चाहते हो?
What was the best part of your week so far? | अब तक तुम्हारे हफ़्ते का सबसे अच्छा हिस्सा क्या रहा?
What is something I did recently that made you smile? | हाल में मेरी किस बात पर तुम मुस्कुराए?
How can I support you this week? | इस हफ़्ते मैं तुम्हारा साथ कैसे दे सकता/सकती हूँ?
What would make tonight perfect? | किस चीज़ से आज की रात परफ़ेक्ट होगी?
What is one thing you love about our routine? | हमारे रूटीन की कौन सी बात तुम्हें पसंद है?
Which of my habits do you secretly love? | मेरी कौन सी आदत तुम्हें चुपके से पसंद है?
What are you grateful for today? | आज तुम किस बात के लिए शुक्रगुज़ार हो?
What's one thing you'd like to try together soon? | जल्द ही साथ में क्या ट्राई करना चाहोगे?
What is a goal we should set as a couple? | एक कपल के रूप में हमें कौन सा लक्ष्य रखना चाहिए?
What's a memory from this month you'll keep? | इस महीने की कौन सी याद तुम संभालकर रखोगे?
What does a perfect evening call look like? | शाम की परफ़ेक्ट कॉल कैसी होती है?
What's one worry I can help you with? | किस चिंता में मैं तुम्हारी मदद कर सकता/सकती हूँ?
What do you love about the way I talk to you? | मेरे बात करने के तरीक़े में तुम्हें क्या अच्छा लगता है?
What song describes your mood today? | कौन सा गाना आज तुम्हारे मूड को बताता है?
What made you laugh the hardest today? | आज तुम सबसे ज़्यादा किस बात पर हँसे?
What do you appreciate about yourself today? | आज तुम्हें अपने बारे में क्या अच्छा लगा?
What should our next weekend look like? | हमारा अगला वीकेंड कैसा होना चाहिए?
What did you dream about last night? | कल रात तुमने क्या सपना देखा?
What is one compliment you want to hear today? | आज तुम कौन सी तारीफ़ सुनना चाहोगे?
Which of our photos would you set as wallpaper today? | हमारी कौन सी फ़ोटो आज वॉलपेपर बनाओगे?
What's something new you'd like us to learn together? | साथ में हम कौन सी नई चीज़ सीखें?
What would you like more of in our relationship? | हमारे रिश्ते में तुम्हें किस चीज़ की और चाहत है?
What would you like less of in our relationship? | हमारे रिश्ते में तुम्हें किस चीज़ की कमी चाहिए?
What did you eat today that you loved? | आज तुमने क्या खाया जो बहुत पसंद आया?
What's a place nearby we haven't explored yet? | पास की कौन सी जगह है जो हमने अभी तक नहीं देखी?
How was your energy today, from 1 to 10? | 1 से 10 में आज तुम्हारी एनर्जी कितनी थी?
What is one thing you're looking forward to tomorrow? | कल की किस बात का तुम्हें इंतज़ार है?
What made you think "I wish you were here" today? | आज किस पल पर तुमने सोचा "काश तुम यहाँ होते"?
What is the kindest thing someone did for you recently? | हाल में किसी ने तुम्हारे लिए सबसे प्यारा काम क्या किया?
What is one thing you want to do on our next meeting? | हमारी अगली मुलाक़ात पर तुम क्या करना चाहोगे?
What's a small win you had today? | आज तुम्हारी छोटी सी जीत क्या रही?
What do you love about weekends with me? | मेरे साथ वीकेंड की कौन सी बात तुम्हें पसंद है?
What would you put on our couple bucket list today? | आज हमारी बकेट लिस्ट में क्या जोड़ोगे?
What's one way I made your life easier lately? | हाल में मैंने तुम्हारी ज़िंदगी कैसे आसान बनाई?
Which season reminds you of us? | कौन सा मौसम तुम्हें हमारी याद दिलाता है?
What's a movie you want to watch together this week? | इस हफ़्ते साथ में कौन सी फ़िल्म देखना चाहोगे?
What's something you're proud of us for? | तुम्हें हम दोनों की किस बात पर गर्व है?
What helps you relax after a long day? | लंबे दिन के बाद तुम्हें क्या चीज़ आराम देती है?
What's a habit you want to start? | तुम कौन सी आदत शुरू करना चाहते हो?
What is the best gift I could give you without spending money? | बिना पैसे ख़र्च किए मैं तुम्हें सबसे अच्छा तोहफ़ा क्या दे सकता/सकती हूँ?
What do you want to hear from me more often? | मुझसे तुम क्या और ज़्यादा सुनना चाहते हो?
What's one fun fact about your day? | आज के दिन की एक मज़ेदार बात बताओ।
What is one thing you want to change this week? | इस हफ़्ते तुम एक चीज़ क्या बदलना चाहते हो?
What's your favourite thing about our chats? | हमारी चैट की कौन सी बात तुम्हें सबसे अच्छी लगती है?
Which of our trips should we repeat? | हमारी कौन सी ट्रिप हमें दोबारा करनी चाहिए?
What made today different from yesterday? | आज का दिन कल से अलग कैसे था?
What is one way we can be more playful? | हम और ज़्यादा मस्ती भरे कैसे बन सकते हैं?
What do you want our story to look like in a year? | एक साल बाद तुम हमारी कहानी कैसी देखना चाहते हो?
What's one thing you love about my voice? | मेरी आवाज़ की कौन सी बात तुम्हें पसंद है?
What's a food we must try together? | ऐसा कौन सा खाना है जो हमें साथ में ज़रूर चखना चाहिए?
When did you feel most at peace today? | आज तुमने सबसे ज़्यादा सुकून कब महसूस किया?
What's a nickname you'd like me to call you? | तुम चाहोगे मैं तुम्हें किस प्यारे नाम से बुलाऊँ?
What would you say to me if we met for the first time today? | अगर हम आज पहली बार मिलते, तो तुम मुझसे क्या कहते?
What's one thing that made you feel loved this week? | इस हफ़्ते किस बात से तुम्हें प्यार महसूस हुआ?
What would you like to celebrate today, big or small? | आज तुम क्या जश्न मनाना चाहोगे, छोटा या बड़ा?
Which moment today would you share only with me? | आज का कौन सा पल तुम सिर्फ़ मेरे साथ बाँटोगे?
What is something you've been meaning to ask me? | ऐसी कौन सी बात है जो तुम मुझसे पूछना चाह रहे थे?
How do you want to be cared for when you're tired? | थके होने पर तुम चाहते हो तुम्हारा ख़याल कैसे रखा जाए?
What's one thing you'd like to do differently tomorrow? | कल तुम एक चीज़ अलग तरीक़े से क्या करना चाहोगे?
TXT)],
  ]],
];
