<?php
/**
 * Starter content: games, question packs, themes, products, settings.
 * Applied by `php backend/bin/seed.php` (safe to run again: matches by slug/text).
 * Admins add and edit content later from the admin panel.
 *
 * Adult content rule: suggestive, romantic and consensual; never explicit, so it
 * stays within Google Play's content policy. Every adult card can be skipped.
 */

$couple = ['@couple'];

$games = [
  // ---------------- FREE ----------------
  ['slug' => 'truth-or-dare', 'title' => 'Truth or Dare', 'mode' => 'prompt', 'icon' => 'flame', 'rounds' => 10,
   'description' => 'Take turns picking truth or dare. Answer honestly or complete the dare, or skip any card.',
   'config' => ['choose_kind' => true],
   'packs' => [
     ['slug' => 'tod-sweet', 'title' => 'Sweet & Silly', 'level' => 'easy', 'questions' => [
       ['truth', 'What was your very first impression of me?'],
       ['truth', 'What is one thing I do that always makes you smile?'],
       ['truth', 'What song reminds you of us?'],
       ['truth', 'When did you first realise you liked me?'],
       ['truth', 'What is the most embarrassing thing you have done to impress me?'],
       ['truth', 'Which of my habits secretly annoys you a little?'],
       ['truth', 'What is your favourite photo of us and why?'],
       ['dare', 'Send me a voice note singing the chorus of our song.'],
       ['dare', 'Describe me using only three emojis.'],
       ['dare', 'Do your best impression of me for 20 seconds (on video or voice note).'],
       ['dare', 'Write a four-line poem about us right now.'],
       ['dare', 'Change your phone wallpaper to a photo of us for a day.'],
       ['dare', 'Send me the last photo in your gallery, no excuses.'],
       ['dare', 'Tell me three things you love about me, in a whisper voice note.'],
     ]],
     ['slug' => 'tod-deeper', 'title' => 'Deeper', 'level' => 'medium', 'questions' => [
       ['truth', 'What is one fear you have about our future?'],
       ['truth', 'When did you feel closest to me?'],
       ['truth', 'Is there something you have wanted to tell me but have not?'],
       ['truth', 'What is one thing you wish we did more often?'],
       ['dare', 'Plan our next date in detail and send it to me in 5 minutes.'],
       ['dare', 'Record a one-minute video telling me your favourite memory of us.'],
       ['dare', 'Let me pick your profile picture for the next 24 hours.'],
     ]],
   ]],
  ['slug' => 'how-well-do-you-know-me', 'title' => 'How Well Do You Know Me?', 'mode' => 'guess', 'icon' => 'brain', 'rounds' => 10,
   'description' => 'One of you answers about yourself, the other guesses. Points for every correct guess.',
   'packs' => [['slug' => 'hwdykm-basics', 'title' => 'The Basics', 'level' => 'easy', 'questions' => [
     ['question', 'What is my comfort food?'],
     ['question', 'What would I order at a café?', ['Coffee', 'Tea', 'Hot chocolate', 'A cold drink']],
     ['question', 'Am I a morning person or a night owl?', ['Morning person', 'Night owl']],
     ['question', 'What is my dream travel destination?'],
     ['question', 'How do I prefer to spend a free Sunday?', ['Out with friends', 'At home relaxing', 'Exploring somewhere new', 'Catching up on work']],
     ['question', 'What is my biggest pet peeve?'],
     ['question', 'Which do I prefer?', ['Mountains', 'Beaches']],
     ['question', 'What was my childhood dream job?'],
     ['question', 'What is my favourite movie genre?', ['Romance', 'Comedy', 'Action', 'Horror', 'Drama']],
     ['question', 'What makes me feel most loved?'],
     ['question', 'What is my go-to karaoke song?'],
     ['question', 'Which season do I like most?', ['Summer', 'Monsoon', 'Winter', 'Spring']],
   ]]]],
  ['slug' => 'who-is-more-likely', 'title' => 'Who Is More Likely To?', 'mode' => 'match', 'icon' => 'users', 'rounds' => 10,
   'description' => 'Both of you pick who fits best. See if you agree.',
   'packs' => [['slug' => 'wiml-classic', 'title' => 'Classic', 'level' => 'easy', 'questions' => [
     ['question', 'Who is more likely to fall asleep during a movie?', $couple],
     ['question', 'Who is more likely to forget an anniversary?', $couple],
     ['question', 'Who is more likely to cry at a sad movie?', $couple],
     ['question', 'Who is more likely to get lost even with maps?', $couple],
     ['question', 'Who is more likely to apologise first after a fight?', $couple],
     ['question', 'Who is more likely to plan a surprise?', $couple],
     ['question', 'Who is more likely to eat the last slice?', $couple],
     ['question', 'Who is more likely to spend too much while shopping?', $couple],
     ['question', 'Who is more likely to text back instantly?', $couple],
     ['question', 'Who is more likely to become famous?', $couple],
     ['question', 'Who is more likely to burn dinner?', $couple],
     ['question', 'Who is more likely to say "I love you" first in a day?', $couple],
   ]]]],
  ['slug' => 'this-or-that', 'title' => 'This or That', 'mode' => 'match', 'icon' => 'shuffle', 'rounds' => 12,
   'description' => 'Quick pick between two options. How often do you match?',
   'packs' => [['slug' => 'tot-quick', 'title' => 'Quick Picks', 'level' => 'easy', 'questions' => [
     ['question', 'Pick one', ['Coffee', 'Tea']], ['question', 'Pick one', ['Movie night', 'Dinner out']],
     ['question', 'Pick one', ['Sunrise', 'Sunset']], ['question', 'Pick one', ['Cats', 'Dogs']],
     ['question', 'Pick one', ['Road trip', 'Flight']], ['question', 'Pick one', ['Pizza', 'Biryani']],
     ['question', 'Pick one', ['Texting', 'Calling']], ['question', 'Pick one', ['Rain', 'Sunshine']],
     ['question', 'Pick one', ['Books', 'Series']], ['question', 'Pick one', ['Sweet', 'Spicy']],
     ['question', 'Pick one', ['City life', 'Village life']], ['question', 'Pick one', ['Plan everything', 'Go with the flow']],
     ['question', 'Pick one', ['Big wedding', 'Small wedding']], ['question', 'Pick one', ['Hugs', 'Kisses']],
   ]]]],
  ['slug' => 'never-have-i-ever', 'title' => 'Never Have I Ever', 'mode' => 'match', 'icon' => 'hand', 'rounds' => 10,
   'description' => 'Both answer "I have" or "Never". Learn surprising things about each other.',
   'packs' => [['slug' => 'nhie-classic', 'title' => 'Classic', 'level' => 'easy', 'questions' => [
     ['statement', 'Never have I ever stalked an ex on social media.', ['I have', 'Never']],
     ['statement', 'Never have I ever pretended to like a gift.', ['I have', 'Never']],
     ['statement', 'Never have I ever sung in the shower loudly.', ['I have', 'Never']],
     ['statement', 'Never have I ever fallen asleep on a call with you.', ['I have', 'Never']],
     ['statement', 'Never have I ever eaten food that fell on the floor.', ['I have', 'Never']],
     ['statement', 'Never have I ever travelled alone.', ['I have', 'Never']],
     ['statement', 'Never have I ever lied about my age.', ['I have', 'Never']],
     ['statement', 'Never have I ever cried at a wedding.', ['I have', 'Never']],
     ['statement', 'Never have I ever read your messages over your shoulder.', ['I have', 'Never']],
     ['statement', 'Never have I ever planned our wedding in my head.', ['I have', 'Never']],
   ]]]],
  ['slug' => 'guess-my-answer', 'title' => 'Guess My Answer', 'mode' => 'guess', 'icon' => 'target', 'rounds' => 10,
   'description' => 'Predict what your partner will pick. Fast, fun, and revealing.',
   'packs' => [['slug' => 'gma-mix', 'title' => 'Mix', 'level' => 'easy', 'questions' => [
     ['question', 'If I could have one superpower, it would be…', ['Flying', 'Invisibility', 'Teleportation', 'Reading minds']],
     ['question', 'My ideal date is…', ['Candlelight dinner', 'Adventure activity', 'Cosy night in', 'Long drive']],
     ['question', 'If I won a lottery, first I would…', ['Travel the world', 'Buy a house', 'Invest it', 'Quit my job']],
     ['question', 'My love language is mostly…', ['Words of affirmation', 'Quality time', 'Gifts', 'Acts of service', 'Physical touch']],
     ['question', 'When I am upset I want you to…', ['Give me space', 'Hug me', 'Talk it out', 'Distract me']],
     ['question', 'My dream pet is…', ['Dog', 'Cat', 'Bird', 'No pets']],
     ['question', 'On a holiday I would rather…', ['Explore all day', 'Relax at the hotel', 'Try local food', 'Shop']],
     ['question', 'The gift I would love most is…', ['Something handmade', 'An experience', 'Jewellery or gadget', 'A letter']],
   ]]]],
  ['slug' => 'relationship-quiz', 'title' => 'Relationship Quiz', 'mode' => 'guess', 'icon' => 'heart', 'rounds' => 10,
   'description' => 'How well do you remember your own love story?',
   'packs' => [['slug' => 'rq-story', 'title' => 'Our Story', 'level' => 'medium', 'questions' => [
     ['question', 'Where did we first meet?'], ['question', 'What did we do on our first date?'],
     ['question', 'Who said "I love you" first?'], ['question', 'What was the first gift I gave you?'],
     ['question', 'What is the date of our anniversary?'], ['question', 'What was our first trip together?'],
     ['question', 'What was our first fight about?'], ['question', 'What nickname did I give you first?'],
     ['question', 'Which restaurant is "our place"?'], ['question', 'What was I wearing when we first met?'],
   ]]]],
  ['slug' => 'memory-challenge', 'title' => 'Memory Challenge', 'mode' => 'guess', 'icon' => 'clock', 'rounds' => 8,
   'description' => 'Test each other on small moments you shared. The one who remembers judges the guess.',
   'packs' => [['slug' => 'mc-moments', 'title' => 'Little Moments', 'level' => 'medium', 'questions' => [
     ['question', 'What did we eat the last time we went out?'], ['question', 'What was the last movie we watched together?'],
     ['question', 'What colour was I wearing on our last date?'], ['question', 'What did I say in my last voice note to you?'],
     ['question', 'Which song did we listen to on our last drive?'], ['question', 'What was the first message I ever sent you?'],
     ['question', 'What was the weather like on our first date?'], ['question', 'What did I get you for your last birthday?'],
   ]]]],
  ['slug' => 'emoji-guess', 'title' => 'Emoji Guessing Game', 'mode' => 'quiz', 'icon' => 'smile', 'rounds' => 10,
   'description' => 'Decode the emoji puzzle. Both answer, both can score.',
   'packs' => [['slug' => 'emoji-movies', 'title' => 'Movies & Songs', 'level' => 'easy', 'questions' => [
     ['question', '🚢🧊💑', ['Titanic', 'Frozen', 'Life of Pi', 'Moana'], 'Titanic'],
     ['question', '🦁👑', ['The Lion King', 'Madagascar', 'Jungle Book', 'Tarzan'], 'The Lion King'],
     ['question', '🧙‍♂️⚡👓', ['Harry Potter', 'Lord of the Rings', 'Doctor Strange', 'Merlin'], 'Harry Potter'],
     ['question', '👻🚫', ['Ghostbusters', 'Casper', 'The Others', 'Coco'], 'Ghostbusters'],
     ['question', '🕷️🧑', ['Spider-Man', 'Ant-Man', 'Batman', 'Venom'], 'Spider-Man'],
     ['question', '❄️👸⛄', ['Frozen', 'Cinderella', 'Brave', 'Tangled'], 'Frozen'],
     ['question', '🦖🏝️', ['Jurassic Park', 'Cast Away', 'King Kong', 'Godzilla'], 'Jurassic Park'],
     ['question', '🐠🔍', ['Finding Nemo', 'Shark Tale', 'The Little Mermaid', 'Aquaman'], 'Finding Nemo'],
     ['question', '🌹🧌', ['Beauty and the Beast', 'Shrek', 'Aladdin', 'Mulan'], 'Beauty and the Beast'],
     ['question', '👨‍🚀🌌⏳', ['Interstellar', 'Gravity', 'The Martian', 'Avatar'], 'Interstellar'],
   ]]]],
  ['slug' => 'would-you-rather', 'title' => 'Would You Rather?', 'mode' => 'match', 'icon' => 'scale', 'rounds' => 10,
   'description' => 'Tough choices, answered together. Do you think alike?',
   'packs' => [['slug' => 'wyr-fun', 'title' => 'Fun', 'level' => 'easy', 'questions' => [
     ['question', 'Would you rather…', ['Travel back in time', 'See the future']],
     ['question', 'Would you rather…', ['Have a tiny house on the beach', 'A big house in the city']],
     ['question', 'Would you rather…', ['Always be 10 minutes late', 'Always be 20 minutes early']],
     ['question', 'Would you rather…', ['Give up sweets', 'Give up spicy food']],
     ['question', 'Would you rather…', ['Have a surprise party', 'Plan your own celebration']],
     ['question', 'Would you rather…', ['Live without music', 'Live without movies']],
     ['question', 'Would you rather…', ['Go on a road trip', 'Go on a cruise']],
     ['question', 'Would you rather…', ['Read each other\'s minds', 'Never argue again']],
     ['question', 'Would you rather…', ['Relive our first date', 'Skip ahead to our 10th anniversary']],
     ['question', 'Would you rather…', ['Cook together every night', 'Eat out every night']],
   ]]]],
  ['slug' => 'complete-my-sentence', 'title' => 'Complete My Sentence', 'mode' => 'guess', 'icon' => 'edit', 'rounds' => 8,
   'description' => 'One finishes the sentence, the other guesses how. The writer judges.',
   'packs' => [['slug' => 'cms-sweet', 'title' => 'Sweet', 'level' => 'easy', 'questions' => [
     ['question', 'The best part of my day is…'], ['question', 'I feel most loved when you…'],
     ['question', 'My favourite thing about us is…'], ['question', 'If we had a free weekend, I would want to…'],
     ['question', 'The first thing I noticed about you was…'], ['question', 'One day I want us to…'],
     ['question', 'I laugh the hardest when you…'], ['question', 'The thing I miss most when we are apart is…'],
   ]]]],
  ['slug' => 'love-language-quiz', 'title' => 'Love Language Quiz', 'mode' => 'match', 'icon' => 'gift', 'rounds' => 8,
   'description' => 'Discover how each of you gives and receives love. See your top love language at the end.',
   'packs' => [['slug' => 'llq', 'title' => 'Love Languages', 'level' => 'easy', 'questions' => [
     ['question', 'After a long day, what helps you most?', [['t' => 'Hearing "I am proud of you"', 'cat' => 'words'], ['t' => 'An hour together, phones away', 'cat' => 'time'], ['t' => 'A small surprise treat', 'cat' => 'gifts'], ['t' => 'Me taking a chore off your hands', 'cat' => 'service'], ['t' => 'A long hug', 'cat' => 'touch']]],
     ['question', 'Which would mean the most on your birthday?', [['t' => 'A heartfelt letter', 'cat' => 'words'], ['t' => 'A whole day planned together', 'cat' => 'time'], ['t' => 'A thoughtful gift', 'cat' => 'gifts'], ['t' => 'Breakfast made for you', 'cat' => 'service'], ['t' => 'Cuddles all morning', 'cat' => 'touch']]],
     ['question', 'What makes you feel ignored?', [['t' => 'No compliments for a long time', 'cat' => 'words'], ['t' => 'Distracted conversations', 'cat' => 'time'], ['t' => 'Forgotten special days', 'cat' => 'gifts'], ['t' => 'Not getting help when busy', 'cat' => 'service'], ['t' => 'No affection', 'cat' => 'touch']]],
     ['question', 'Your favourite way to say sorry?', [['t' => 'Saying it sincerely', 'cat' => 'words'], ['t' => 'Spending time to fix things', 'cat' => 'time'], ['t' => 'A peace-offering gift', 'cat' => 'gifts'], ['t' => 'Doing something helpful', 'cat' => 'service'], ['t' => 'A hug', 'cat' => 'touch']]],
     ['question', 'When we are apart, what do you miss most?', [['t' => 'Sweet messages', 'cat' => 'words'], ['t' => 'Doing nothing together', 'cat' => 'time'], ['t' => 'Little surprises', 'cat' => 'gifts'], ['t' => 'Taking care of each other', 'cat' => 'service'], ['t' => 'Holding hands', 'cat' => 'touch']]],
     ['question', 'The best compliment is…', [['t' => 'Being told I am loved', 'cat' => 'words'], ['t' => 'Being someone\'s priority', 'cat' => 'time'], ['t' => 'Being remembered with a gift', 'cat' => 'gifts'], ['t' => 'Being supported in action', 'cat' => 'service'], ['t' => 'Being held close', 'cat' => 'touch']]],
   ]]]],
  ['slug' => 'romantic-questions', 'title' => 'Romantic Questions', 'mode' => 'prompt', 'icon' => 'heart', 'rounds' => 8,
   'description' => 'Both answer the same question, then see each other\'s answers.',
   'config' => ['both_answer' => true],
   'packs' => [['slug' => 'rom-q', 'title' => 'Romantic', 'level' => 'easy', 'questions' => [
     ['question', 'What moment made you sure about us?'], ['question', 'What is your favourite way to spend time with me?'],
     ['question', 'Describe our perfect day together.'], ['question', 'What is something small I do that means a lot to you?'],
     ['question', 'Where do you see us in five years?'], ['question', 'What song would you dedicate to me?'],
     ['question', 'What is one promise you want to make to me?'], ['question', 'Which memory of us do you replay the most?'],
     ['question', 'What do you admire most about me?'], ['question', 'What adventure should we go on together?'],
   ]]]],
  ['slug' => 'daily-couple-challenge', 'title' => 'Daily Couple Challenge', 'mode' => 'prompt', 'icon' => 'trophy', 'rounds' => 1,
   'description' => 'One small challenge to do together today. Mark it done when you have.',
   'config' => ['both_answer' => true],
   'packs' => [['slug' => 'dcc', 'title' => 'Daily', 'level' => 'easy', 'questions' => [
     ['challenge', 'Send each other a photo of something that reminded you of the other today.'],
     ['challenge', 'Write three things you are grateful for about your partner.'],
     ['challenge', 'Have a 10-minute call with no phones or screens around.'],
     ['challenge', 'Cook or order the same dish and eat "together" on video.'],
     ['challenge', 'Share a song the other has never heard.'],
     ['challenge', 'Plan one tiny surprise for tomorrow.'],
     ['challenge', 'Recreate your first selfie together (or a new version of it).'],
     ['challenge', 'Tell each other one goal for this week and how to support it.'],
   ]]]],
  ['slug' => 'guess-the-memory', 'title' => 'Guess The Memory', 'mode' => 'guess', 'icon' => 'image', 'rounds' => 6,
   'description' => 'Describe a shared memory with one clue; your partner guesses which one it is.',
   'packs' => [['slug' => 'gtm', 'title' => 'Clues', 'level' => 'medium', 'questions' => [
     ['question', 'Give a one-word clue about a memory from our first month. Partner guesses the memory.'],
     ['question', 'Describe a trip memory using only a smell or sound.'],
     ['question', 'Name a food that reminds you of a moment with me.'],
     ['question', 'Give a song title that brings back a memory of us.'],
     ['question', 'Name a place and let me guess what happened there.'],
     ['question', 'Describe a funny memory in five words.'],
   ]]]],
  ['slug' => 'two-truths-and-a-lie', 'title' => 'Two Truths and a Lie', 'mode' => 'guess', 'icon' => 'mask', 'rounds' => 6,
   'description' => 'Write three statements about yourself, one false. Can your partner spot the lie?',
   'packs' => [['slug' => 'ttal', 'title' => 'About Me', 'level' => 'easy', 'questions' => [
     ['two_truths', 'Three statements about your childhood.'], ['two_truths', 'Three statements about your school or college days.'],
     ['two_truths', 'Three statements about your food habits.'], ['two_truths', 'Three statements about things you have done for me.'],
     ['two_truths', 'Three statements about your secret talents.'], ['two_truths', 'Three statements about your dreams.'],
   ]]]],
  ['slug' => 'compatibility-quiz', 'title' => 'Couple Compatibility Quiz', 'mode' => 'match', 'icon' => 'link', 'rounds' => 10,
   'description' => 'Answer separately and get a compatibility score at the end. Just for fun.',
   'packs' => [['slug' => 'cq', 'title' => 'Compatibility', 'level' => 'easy', 'questions' => [
     ['question', 'Ideal number of kids?', ['None', 'One', 'Two', 'Three or more', 'Not sure yet']],
     ['question', 'Where should we live?', ['Big city', 'Small town', 'Abroad', 'Anywhere together']],
     ['question', 'Money style?', ['Save first', 'Spend and enjoy', 'Balanced']],
     ['question', 'Weekend energy?', ['Social plans', 'Quiet at home', 'Outdoors']],
     ['question', 'How should we handle fights?', ['Talk immediately', 'Cool off first', 'Write it down']],
     ['question', 'Holiday style?', ['Adventure', 'Relaxation', 'Culture and food']],
     ['question', 'Bedtime?', ['Before 11', 'Around midnight', 'Way later']],
     ['question', 'Pets at home?', ['Yes, many', 'One is enough', 'No pets']],
     ['question', 'Celebrations?', ['Big parties', 'Just us two', 'Small family gatherings']],
     ['question', 'Chores?', ['Split equally', 'Each picks favourites', 'Do them together']],
   ]]]],
  ['slug' => 'date-night-ideas', 'title' => 'Date Night Ideas', 'mode' => 'match', 'icon' => 'calendar', 'rounds' => 12,
   'description' => 'Swipe through date ideas. Ideas you both love become your shortlist.',
   'packs' => [['slug' => 'dni', 'title' => 'Ideas', 'level' => 'easy', 'questions' => [
     ['question', 'Picnic at sunset', ['Love it', 'Not for me']], ['question', 'Cooking a new recipe together', ['Love it', 'Not for me']],
     ['question', 'Stargazing night', ['Love it', 'Not for me']], ['question', 'Board game night', ['Love it', 'Not for me']],
     ['question', 'Pottery or painting class', ['Love it', 'Not for me']], ['question', 'Long drive with a playlist', ['Love it', 'Not for me']],
     ['question', 'Virtual museum tour on video call', ['Love it', 'Not for me']], ['question', 'Karaoke night', ['Love it', 'Not for me']],
     ['question', 'Watch the same movie in sync', ['Love it', 'Not for me']], ['question', 'Breakfast date', ['Love it', 'Not for me']],
     ['question', 'Write letters and read them aloud', ['Love it', 'Not for me']], ['question', 'Try street food in a new area', ['Love it', 'Not for me']],
   ]]]],
  ['slug' => 'random-questions', 'title' => 'Random Question Generator', 'mode' => 'prompt', 'icon' => 'dice', 'rounds' => 10,
   'description' => 'Random conversation starters. Both answer, then compare.',
   'config' => ['both_answer' => true],
   'packs' => [['slug' => 'rqg', 'title' => 'Random', 'level' => 'easy', 'questions' => [
     ['question', 'If you could have dinner with anyone, who would it be?'], ['question', 'What is a skill you want to learn this year?'],
     ['question', 'What is the best advice you have ever received?'], ['question', 'Which fictional world would you live in?'],
     ['question', 'What is your most-used emoji and why?'], ['question', 'What would your perfect house look like?'],
     ['question', 'What is a small thing that makes you really happy?'], ['question', 'What did you want to be when you were ten?'],
     ['question', 'Which smell instantly takes you back to childhood?'], ['question', 'What is something you are proud of this month?'],
   ]]]],
  ['slug' => 'daily-relationship-questions', 'title' => 'Daily Relationship Questions', 'mode' => 'prompt', 'icon' => 'sun', 'rounds' => 5,
   'description' => 'A new question every day. Answer to unlock your partner\'s answer and keep your streak.',
   'config' => ['both_answer' => true],
   'packs' => [['slug' => 'daily-questions', 'title' => 'Daily', 'level' => 'easy', 'questions' => [
     ['question', 'What made you think of me today?'], ['question', 'What is one thing you want to do together this month?'],
     ['question', 'What is your favourite thing about our relationship right now?'], ['question', 'What is something you have never told me?'],
     ['question', 'Which of my qualities do you hope our kids get?'], ['question', 'What is a place you want to visit with me?'],
     ['question', 'What is the sweetest thing I have ever done for you?'], ['question', 'What does a perfect lazy day with me look like?'],
     ['question', 'What is one thing I can do to make your week easier?'], ['question', 'Which song is stuck in your head today?'],
     ['question', 'What is one tradition you want us to start?'], ['question', 'What was the highlight of your day?'],
     ['question', 'What is something new you learned about me recently?'], ['question', 'If we had a theme song, what would it be?'],
     ['question', 'What is your favourite photo of us?'], ['question', 'What is one fear you would like my help with?'],
     ['question', 'What would you put in a time capsule about us?'], ['question', 'Which moment of ours would you frame?'],
     ['question', 'How do you like to be comforted when sad?'], ['question', 'What is something you want to thank me for?'],
     ['question', 'What does home mean to you?'], ['question', 'Which of our inside jokes is your favourite?'],
     ['question', 'What is a dream you have not shared yet?'], ['question', 'What should we celebrate this week?'],
     ['question', 'What was your first thought this morning?'], ['question', 'What is one way we have grown together?'],
     ['question', 'Which movie should we watch next?'], ['question', 'What would you cook for me on a special day?'],
     ['question', 'What do you love about how we communicate?'], ['question', 'What is one thing you are looking forward to?'],
   ]]]],

  // ---------------- ADULT (18+, premium, both partners opt in) ----------------
  ['slug' => 'adult-truth-or-dare', 'section' => 'adult', 'title' => 'Truth or Dare After Dark', 'mode' => 'prompt', 'icon' => 'flame', 'rounds' => 10,
   'description' => 'A flirtier truth or dare for two. Pick your level, skip anything, stop any time.',
   'config' => ['choose_kind' => true],
   'packs' => [
     ['slug' => 'atod-flirty', 'title' => 'Flirty', 'level' => 'flirty', 'questions' => [
       ['truth', 'What outfit of mine do you find most attractive?'], ['truth', 'When did you last daydream about me?'],
       ['truth', 'What is the most flirty text you almost sent me?'], ['truth', 'Which of my features do you notice first?'],
       ['dare', 'Send me a selfie with your most charming look.'], ['dare', 'Record a voice note saying my name the way you like best.'],
       ['dare', 'Describe our next date night in your most romantic voice.'],
     ]],
     ['slug' => 'atod-bold', 'title' => 'Bold', 'level' => 'bold', 'questions' => [
       ['truth', 'What is one romantic thing you have always wanted us to try?'], ['truth', 'Where is the most unexpected place you wanted to kiss me?'],
       ['truth', 'What is something I do that drives you a little wild?'],
       ['dare', 'Tell me, in detail, how you would greet me after a month apart.'], ['dare', 'Write me a short note I can only open tonight.'],
       ['dare', 'Choose the outfit I should wear for our next date.'],
     ]],
     ['slug' => 'atod-spicy', 'title' => 'Spicy', 'level' => 'spicy', 'questions' => [
       ['truth', 'What is your favourite memory of a kiss with me?'], ['truth', 'What makes you feel most desired by me?'],
       ['dare', 'Plan a private date night for us and send me the invitation.'], ['dare', 'Whisper (in a voice note) what you are looking forward to next time we meet.'],
     ]],
   ]],
  ['slug' => 'intimacy-question-cards', 'section' => 'adult', 'title' => 'Intimacy Question Cards', 'mode' => 'prompt', 'icon' => 'cards', 'rounds' => 8,
   'description' => 'Deep, honest questions about closeness and desire. Both answer, then reveal.',
   'config' => ['both_answer' => true],
   'packs' => [
     ['slug' => 'iqc-romantic', 'title' => 'Romantic', 'level' => 'romantic', 'questions' => [
       ['question', 'When do you feel closest to me physically and emotionally?'], ['question', 'What does a perfect romantic evening look like to you?'],
       ['question', 'What kind of affection do you wish you got more of?'], ['question', 'Which moment between us felt the most intimate?'],
     ]],
     ['slug' => 'iqc-intimate', 'title' => 'Intimate', 'level' => 'intimate', 'questions' => [
       ['question', 'What helps you feel relaxed and safe with me?'], ['question', 'What is something new you would like us to explore together, at your pace?'],
       ['question', 'What is your favourite way for me to show I want you?'], ['question', 'Is there anything you would like us to slow down or change?'],
     ]],
   ]],
  ['slug' => 'spicy-would-you-rather', 'section' => 'adult', 'title' => 'Spicy Would You Rather', 'mode' => 'match', 'icon' => 'scale', 'rounds' => 10,
   'description' => 'Flirty choices for two. See where your desires match.',
   'packs' => [['slug' => 'swyr-flirty', 'title' => 'Flirty', 'level' => 'flirty', 'questions' => [
     ['question', 'Would you rather…', ['A slow dance at home', 'A candlelit dinner']],
     ['question', 'Would you rather…', ['Be surprised with a kiss', 'Be asked for one']],
     ['question', 'Would you rather…', ['Get a massage', 'Give a massage']],
     ['question', 'Would you rather…', ['A weekend getaway for two', 'A secret date in the city']],
     ['question', 'Would you rather…', ['Love notes hidden around the house', 'A flirty text all day']],
     ['question', 'Would you rather…', ['Matching outfits on a date', 'Dress up for each other']],
     ['question', 'Would you rather…', ['A bubble bath together', 'A rooftop night under the stars']],
     ['question', 'Would you rather…', ['Plan the romance', 'Be swept off your feet']],
   ]]]],
  ['slug' => 'flirty-conversation-starters', 'section' => 'adult', 'title' => 'Flirty Conversation Starters', 'mode' => 'prompt', 'icon' => 'chat', 'rounds' => 8,
   'description' => 'Playful prompts to turn up the flirt in your chats.',
   'config' => ['both_answer' => true],
   'packs' => [['slug' => 'fcs', 'title' => 'Flirty', 'level' => 'flirty', 'questions' => [
     ['question', 'What would you whisper to me right now if I were next to you?'], ['question', 'Describe the moment you found me most irresistible.'],
     ['question', 'Which of my looks melts you instantly?'], ['question', 'What is a compliment you have been saving for me?'],
     ['question', 'If we had a secret signal, what would it be?'], ['question', 'Rate how much you miss me right now, and why.'],
   ]]]],
  ['slug' => 'romantic-couple-challenges', 'section' => 'adult', 'title' => 'Romantic Couple Challenges', 'mode' => 'prompt', 'icon' => 'star', 'rounds' => 6,
   'description' => 'Romantic missions for two. Do them together, mark them done.',
   'config' => ['both_answer' => true],
   'packs' => [['slug' => 'rcc', 'title' => 'Romantic', 'level' => 'romantic', 'questions' => [
     ['challenge', 'Slow dance to our song tonight (in person or on video).'], ['challenge', 'Write a love letter and read it aloud to each other.'],
     ['challenge', 'Recreate our first date as closely as you can.'], ['challenge', 'Give each other ten genuine compliments, taking turns.'],
     ['challenge', 'Plan a surprise evening with three things your partner loves.'], ['challenge', 'Spend 30 minutes just holding each other, no phones.'],
   ]]]],
  ['slug' => 'private-confessions', 'section' => 'adult', 'title' => 'Private Couple Confessions', 'mode' => 'prompt', 'icon' => 'lock', 'rounds' => 6,
   'description' => 'Share little secrets you have never said out loud. Only you two ever see them.',
   'config' => ['both_answer' => true],
   'packs' => [['slug' => 'pc', 'title' => 'Confessions', 'level' => 'bold', 'questions' => [
     ['question', 'Confess a moment you wanted to kiss me but did not.'], ['question', 'Confess something about me that you find secretly attractive.'],
     ['question', 'Confess a romantic idea you have been too shy to suggest.'], ['question', 'Confess the most romantic dream you have had about us.'],
     ['question', 'Confess when you were most jealous, and why.'], ['question', 'Confess something you want more of in our relationship.'],
   ]]]],
  ['slug' => 'fantasy-questions', 'section' => 'adult', 'title' => 'Couple Fantasy Questions', 'mode' => 'prompt', 'icon' => 'moon', 'rounds' => 6,
   'description' => 'Dream out loud together: getaways, moments and scenarios you would love to share.',
   'config' => ['both_answer' => true],
   'packs' => [['slug' => 'fq', 'title' => 'Dreams', 'level' => 'intimate', 'questions' => [
     ['question', 'Describe your dream romantic getaway with me.'], ['question', 'What is your idea of a perfect night in with me?'],
     ['question', 'If we could relive one intimate moment, which would you choose?'], ['question', 'What setting makes you feel most romantic?'],
     ['question', 'What would a "yes day" for each other look like?'], ['question', 'What is a romantic surprise you would love to receive?'],
   ]]]],
  ['slug' => 'date-night-challenges', 'section' => 'adult', 'title' => 'Private Date Night Challenges', 'mode' => 'prompt', 'icon' => 'wine', 'rounds' => 6,
   'description' => 'Challenges to make your next date night unforgettable.',
   'config' => ['both_answer' => true],
   'packs' => [['slug' => 'dnc', 'title' => 'Date Night', 'level' => 'romantic', 'questions' => [
     ['challenge', 'Dress up for each other as if it were your first date.'], ['challenge', 'Cook a dinner together by candlelight.'],
     ['challenge', 'Make a playlist for the evening, alternating songs.'], ['challenge', 'Trade three questions you have never asked before.'],
     ['challenge', 'Give each other a relaxing shoulder massage.'], ['challenge', 'End the night by sharing what you loved most about it.'],
   ]]]],
  ['slug' => 'roleplay-prompts', 'section' => 'adult', 'title' => 'Romantic Roleplay Prompts', 'mode' => 'prompt', 'icon' => 'theater', 'rounds' => 5,
   'description' => 'Light, playful story starters to act out together, only as far as you both like.',
   'config' => ['both_answer' => true],
   'packs' => [['slug' => 'rp', 'title' => 'Story Starters', 'level' => 'flirty', 'questions' => [
     ['challenge', 'You are strangers meeting at a café. Start the conversation with a pickup line.'],
     ['challenge', 'You are reuniting at an airport after a year apart. Text the first message.'],
     ['challenge', 'One of you is a travel guide, the other a charming tourist. Plan the tour.'],
     ['challenge', 'You are writing letters as lovers in the 1950s. Write the first one.'],
     ['challenge', 'You are on a secret date nobody knows about. Plan your escape.'],
   ]]]],
  ['slug' => 'private-couple-quiz', 'section' => 'adult', 'title' => 'Private Couple Quiz', 'mode' => 'guess', 'icon' => 'key', 'rounds' => 8,
   'description' => 'How well do you know your partner\'s romantic side?',
   'packs' => [['slug' => 'pcq', 'title' => 'Romantic Side', 'level' => 'flirty', 'questions' => [
     ['question', 'My favourite kind of kiss is…', ['Soft and slow', 'Surprise kiss', 'Forehead kiss', 'Kiss goodnight']],
     ['question', 'The most romantic time of day for me is…', ['Morning', 'Afternoon', 'Evening', 'Late night']],
     ['question', 'My ideal romantic setting is…', ['Beach', 'Mountains', 'At home', 'Fancy hotel']],
     ['question', 'What I find most attractive about you is…'],
     ['question', 'The outfit of yours I like most is…'],
     ['question', 'My favourite way to be flirted with is…', ['Compliments', 'Teasing', 'Touch', 'Surprises']],
   ]]]],
  ['slug' => 'long-distance-intimacy', 'section' => 'adult', 'title' => 'Long-Distance Intimacy Challenges', 'mode' => 'prompt', 'icon' => 'globe', 'rounds' => 6,
   'description' => 'Stay close when you are apart: romantic missions made for distance.',
   'config' => ['both_answer' => true],
   'packs' => [['slug' => 'ldi', 'title' => 'Apart Together', 'level' => 'romantic', 'questions' => [
     ['challenge', 'Fall asleep together on a call tonight.'], ['challenge', 'Send a voice note describing what you would do on our next reunion day.'],
     ['challenge', 'Wear something the other picked for your next video call.'], ['challenge', 'Write a letter to be opened the day you meet next.'],
     ['challenge', 'Have a candlelit video dinner, both dressed up.'], ['challenge', 'Share one photo that shows how much you miss each other.'],
   ]]]],
];

$themes = [
  ['default', 'Default', 0, ['primary' => '#E8456B', 'background' => '#FFF7F9', 'surface' => '#FFFFFF', 'text' => '#2A1B22', 'bubble_mine' => '#E8456B', 'bubble_theirs' => '#F3E6EA', 'dark' => false]],
  ['light', 'Light', 0, ['primary' => '#5B6CFF', 'background' => '#FFFFFF', 'surface' => '#F6F7FB', 'text' => '#1B1D29', 'bubble_mine' => '#5B6CFF', 'bubble_theirs' => '#EEF0F6', 'dark' => false]],
  ['dark', 'Dark', 0, ['primary' => '#FF6B8B', 'background' => '#121014', 'surface' => '#1E1B21', 'text' => '#F3EEF1', 'bubble_mine' => '#C23B5E', 'bubble_theirs' => '#2A262D', 'dark' => true]],
  ['minimal', 'Minimal', 0, ['primary' => '#222222', 'background' => '#FAFAFA', 'surface' => '#FFFFFF', 'text' => '#111111', 'bubble_mine' => '#222222', 'bubble_theirs' => '#ECECEC', 'dark' => false]],
  ['romantic-pink', 'Romantic Pink', 1, ['primary' => '#FF4F8B', 'background' => '#FFE9F1', 'surface' => '#FFF5F9', 'text' => '#3A1424', 'bubble_mine' => '#FF4F8B', 'bubble_theirs' => '#FFFFFF', 'gradient' => ['#FF9EC1', '#FF4F8B'], 'dark' => false]],
  ['midnight-love', 'Midnight Love', 1, ['primary' => '#B57BFF', 'background' => '#0D0B1E', 'surface' => '#1A1636', 'text' => '#EDE8FF', 'bubble_mine' => '#7B4DFF', 'bubble_theirs' => '#25204A', 'gradient' => ['#1A1636', '#3B1F6B'], 'dark' => true]],
  ['rose-gold', 'Rose Gold', 1, ['primary' => '#B76E79', 'background' => '#FBF1EE', 'surface' => '#FFFFFF', 'text' => '#3B2A2A', 'bubble_mine' => '#C98A84', 'bubble_theirs' => '#F3E3DE', 'gradient' => ['#F4C7B8', '#B76E79'], 'dark' => false]],
  ['luxury-black', 'Luxury Black', 1, ['primary' => '#D4AF37', 'background' => '#0A0A0A', 'surface' => '#161616', 'text' => '#F5F1E6', 'bubble_mine' => '#3A3220', 'bubble_theirs' => '#1F1F1F', 'dark' => true]],
  ['purple-dream', 'Purple Dream', 1, ['primary' => '#8E44FF', 'background' => '#F4EEFF', 'surface' => '#FFFFFF', 'text' => '#24124A', 'bubble_mine' => '#8E44FF', 'bubble_theirs' => '#E8DDFF', 'gradient' => ['#C3A3FF', '#8E44FF'], 'dark' => false]],
  ['red-passion', 'Red Passion', 1, ['primary' => '#D7263D', 'background' => '#1A0A0D', 'surface' => '#2A1015', 'text' => '#FBEAEC', 'bubble_mine' => '#D7263D', 'bubble_theirs' => '#3A1A20', 'dark' => true]],
  ['ocean-blue', 'Ocean Blue', 1, ['primary' => '#0077B6', 'background' => '#EAF6FB', 'surface' => '#FFFFFF', 'text' => '#0B2A3A', 'bubble_mine' => '#0096C7', 'bubble_theirs' => '#D7EEF7', 'gradient' => ['#90E0EF', '#0077B6'], 'dark' => false]],
  ['sunset', 'Sunset', 1, ['primary' => '#FF6F3C', 'background' => '#FFF1E6', 'surface' => '#FFFFFF', 'text' => '#3A1E10', 'bubble_mine' => '#FF6F3C', 'bubble_theirs' => '#FFE1CC', 'gradient' => ['#FFB347', '#FF5E62'], 'dark' => false]],
  ['galaxy', 'Galaxy', 1, ['primary' => '#7F5AF0', 'background' => '#0B0C1F', 'surface' => '#16173A', 'text' => '#E6E6FF', 'bubble_mine' => '#5A3FD8', 'bubble_theirs' => '#22244F', 'gradient' => ['#0B0C1F', '#3A1C71', '#D76D77'], 'dark' => true]],
  ['glass', 'Premium Glassmorphism', 1, ['primary' => '#FF7AA2', 'background' => '#1C1A2E', 'surface' => 'rgba(255,255,255,0.12)', 'text' => '#FFFFFF', 'bubble_mine' => 'rgba(255,122,162,0.55)', 'bubble_theirs' => 'rgba(255,255,255,0.16)', 'blur' => 18, 'gradient' => ['#41295A', '#2F0743'], 'dark' => true]],
];

$products = [
  ['sku' => 'premium_lifetime', 'title' => 'Couple Connect Premium', 'description' => 'Lifetime access to 18+ games, premium packs and premium themes. One-time payment.', 'price_paise' => 19900, 'grants' => ['premium', 'themes'], 'play_product_id' => 'premium_lifetime'],
];

// More packs and new games live in seeds/more/*.php. An entry with 'game' adds packs to an existing
// game (or questions to an existing pack with the same slug); an entry with 'slug' is a whole new game.
foreach (glob(__DIR__ . '/more/[!_]*.php') ?: [] as $moreFile) {
    foreach (require $moreFile as $entry) {
        if (isset($entry['game'])) {
            foreach ($games as &$g) {
                if ($g['slug'] !== $entry['game']) {
                    continue;
                }
                foreach ($entry['packs'] as $newPack) {
                    $found = false;
                    foreach ($g['packs'] as &$p) {
                        if ($p['slug'] === $newPack['slug']) {
                            $p['questions'] = array_merge($p['questions'], $newPack['questions']);
                            $found = true;
                        }
                    }
                    unset($p);
                    if (!$found) {
                        $g['packs'][] = $newPack;
                    }
                }
            }
            unset($g);
        } else {
            $games[] = $entry;
        }
    }
}

// Hindi for the original starter content above (game names, packs, questions, options)
$hi = is_file(__DIR__ . '/hi.php') ? require __DIR__ . '/hi.php' : [];

return ['games' => $games, 'themes' => $themes, 'products' => $products, 'hi' => $hi];
