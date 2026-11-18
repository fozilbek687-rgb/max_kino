<?php
// Telegram yuborgan JSON ma'lumotni qabul qilish
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// ===================================================================
// BOT TOKEN VA ADMIN ID (TO'G'RIDAN-TO'G'RI SOZLangan)
// ===================================================================
$token = "8925294521:AAGKetqd6psNOThk69gJntK75RegTg6NXiU"; // Bot Tokeningiz
$admin_id = 1661684479; // Admin ID raqamingiz

// Ma'lumotlarni saqlash fayllari (JSON)
$db_file = "movies.json";
$channels_file = "channels.json";
$state_file = "states.json";
$users_file = "users.json";

// JSON fayllardan ma'lumotlarni o'qish
$movies = file_exists($db_file) ? json_decode(file_get_contents($db_file), true) : [];
$channels = file_exists($channels_file) ? json_decode(file_get_contents($channels_file), true) : [];
$states = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : [];
$users = file_exists($users_file) ? json_decode(file_get_contents($users_file), true) : [];

// Yordamchi saqlash funksiyasi
function saveData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// TEZKOR cURL FUNKSIYASI
function bot($method, $data = [], $token) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . $token . "/" . $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// YOPIQ KANAL SO'ROVINI AVTO-QABUL QILISH
if (isset($update["chat_join_request"])) {
    $req = $update["chat_join_request"];
    $chat_id = $req["chat"]["id"];
    $user_id = $req["from"]["id"];

    bot('approveChatJoinRequest', [
        'chat_id' => $chat_id,
        'user_id' => $user_id
    ], $token);

    exit();
}

// REAL-TIME (JONLI) OBUNA TEKSHIRISH FUNKSIYASI
function checkSubscription($user_id, $channels, $token) {
    if (empty($channels)) return true;

    foreach ($channels as $ch) {
        $res = bot('getChatMember', ['chat_id' => $ch['id'], 'user_id' => $user_id], $token);
        
        if (isset($res['ok']) && $res['ok'] === true && isset($res['result']['status'])) {
            $status = $res['result']['status'];
            if (in_array($status, ['left', 'kicked'])) {
                return false;
            }
        } else {
            return false; 
        }
    }

    return true;
}

// Inline tugmalar: Obuna
function getSubscribeKeyboard($channels) {
    $buttons = [];
    foreach ($channels as $ch) {
        $buttons[] = [['text' => $ch['name'], 'url' => $ch['url']]];
    }
    $buttons[] = [['text' => "✅ Obunani tekshirish", 'callback_data' => "check_sub"]];
    return json_encode(['inline_keyboard' => $buttons]);
}

// Inline tugmalar: Admin panel
function getAdminKeyboard() {
    return json_encode([
        'inline_keyboard' => [
            [['text' => "🎬 Yangi kino qo'shish", 'callback_data' => "add_movie"], ['text' => "🗑 Kinoni o'chirish", 'callback_data' => "del_movie"]],
            [['text' => "📢 Kanal qo'shish", 'callback_data' => "add_channel"], ['text' => "❌ Kanalni o'chirish", 'callback_data' => "del_channel"]],
            [['text' => "📋 Kanallar ro'yxati", 'callback_data' => "list_channels"], ['text' => "📊 Statistika", 'callback_data' => "stats"]]
        ]
    ]);
}

// --- CALLBACK QUERY ---
if (isset($update["callback_query"])) {
    $call = $update["callback_query"];
    $user_id = $call["from"]["id"];
    $data = $call["data"];
    $message_id = $call["message"]["message_id"];
    
    if ($data == "check_sub") {
        if (checkSubscription($user_id, $channels, $token)) {
            bot('deleteMessage', ['chat_id' => $user_id, 'message_id' => $message_id], $token);
            bot('sendMessage', [
                'chat_id' => $user_id, 
                'text' => "🎉 Obunangiz tasdiqlandi!\n\nEndi kino kodini yuborishingiz mumkin."
            ], $token);
        } else {
            bot('answerCallbackQuery', [
                'callback_query_id' => $call['id'], 
                'text' => "❌ Siz hali barcha kanallarga obuna bo'lmadingiz!", 
                'show_alert' => true
            ], $token);
        }
    }

    if ($user_id == $admin_id) {
        if ($data == "stats") {
            $user_count = count($users);
            $movie_count = count($movies);
            $channel_count = count($channels);
            $text = "📊 <b>Bot statistikasi:</b>\n\n👤 Obunachilar: {$user_count} ta\n🎬 Kinolar soni: {$movie_count} ta\n📢 Kanallar: {$channel_count} ta";
            bot('sendMessage', ['chat_id' => $user_id, 'text' => $text, 'parse_mode' => 'HTML'], $token);
            bot('answerCallbackQuery', ['callback_query_id' => $call['id']], $token);
        }

        if ($data == "list_channels") {
            if (empty($channels)) {
                bot('sendMessage', ['chat_id' => $user_id, 'text' => "📭 Hozircha majburiy kanallar yo'q."], $token);
            } else {
                $text = "📋 <b>Majburiy kanallar ro'yxati:</b>\n\n";
                foreach ($channels as $ch) {
                    $text .= "🔹 <b>Nomi:</b> {$ch['name']}\n🆔 <b>ID:</b> <code>{$ch['id']}</code>\n🔗 <b>Link:</b> {$ch['url']}\n\n";
                }
                bot('sendMessage', ['chat_id' => $user_id, 'text' => $text, 'parse_mode' => 'HTML'], $token);
            }
            bot('answerCallbackQuery', ['callback_query_id' => $call['id']], $token);
        }

        if ($data == "add_movie") {
            $states[$user_id] = ['step' => 'waiting_for_video'];
            saveData($state_file, $states);
            bot('sendMessage', ['chat_id' => $user_id, 'text' => "📥 Iltimos, kino videosini botga yuboring:"], $token);
            bot('answerCallbackQuery', ['callback_query_id' => $call['id']], $token);
        }

        if ($data == "del_movie") {
            $states[$user_id] = ['step' => 'waiting_for_del_code'];
            saveData($state_file, $states);
            bot('sendMessage', ['chat_id' => $user_id, 'text' => "🗑 O'chirmoqchi bo'lgan kino kodini yuboring:"], $token);
            bot('answerCallbackQuery', ['callback_query_id' => $call['id']], $token);
        }

        if ($data == "add_channel") {
            $states[$user_id] = ['step' => 'waiting_for_ch_id'];
            saveData($state_file, $states);
            bot('sendMessage', ['chat_id' => $user_id, 'text' => "📥 Yangi kanalning <b>ID raqamini</b> yuboring (masalan: <code>-1002052529432</code>):", 'parse_mode' => 'HTML'], $token);
            bot('answerCallbackQuery', ['callback_query_id' => $call['id']], $token);
        }

        if ($data == "del_channel") {
            $states[$user_id] = ['step' => 'waiting_for_del_ch_id'];
            saveData($state_file, $states);
            bot('sendMessage', ['chat_id' => $user_id, 'text' => "🗑 O'chirmoqchi bo'lgan kanalning <b>ID raqamini</b> yuboring:"], $token);
            bot('answerCallbackQuery', ['callback_query_id' => $call['id']], $token);
        }
    }
    exit();
}

// --- MESSAGE ---
if (isset($update["message"])) {
    $msg = $update["message"];
    $user_id = $msg["from"]["id"];
    $text = isset($msg["text"]) ? trim($msg["text"]) : '';
    $user_state = isset($states[$user_id]) ? $states[$user_id] : null;

    if (!in_array($user_id, $users)) {
        $users[] = $user_id;
        saveData($users_file, $users);
    }

    if ($text == "/start" || $text == "/admin") {
        unset($states[$user_id]);
        saveData($state_file, $states);
        
        if ($user_id == $admin_id) {
            bot('sendMessage', [
                'chat_id' => $user_id, 
                'text' => "👑 Admin paneli:", 
                'reply_markup' => getAdminKeyboard()
            ], $token);
        } else {
            if (checkSubscription($user_id, $channels, $token)) {
                bot('sendMessage', [
                    'chat_id' => $user_id, 
                    'text' => "👋 Xush kelibsiz!\n\nKino olish uchun uning kodini yuboring (masalan: 101):"
                ], $token);
            } else {
                bot('sendMessage', [
                    'chat_id' => $user_id, 
                    'text' => "⚠️ Botdan foydalanish uchun avval quyidagi kanalimizga obuna bo'ling:", 
                    'reply_markup' => getSubscribeKeyboard($channels)
                ], $token);
            }
        }
        exit();
    }

    // ADMIN FSM HOLATLARI
    if ($user_id == $admin_id && $user_state) {
        $step = $user_state['step'];

        if ($step == 'waiting_for_video' && isset($msg['video'])) {
            $file_id = $msg['video']['file_id'];
            $states[$user_id] = ['step' => 'waiting_for_code', 'file_id' => $file_id];
            saveData($state_file, $states);
            bot('sendMessage', ['chat_id' => $user_id, 'text' => "✅ Video qabul qilindi. Endi kino uchun <b>KOD</b> kiriting:", 'parse_mode' => 'HTML'], $token);
            exit();
        }

        if ($step == 'waiting_for_code' && $text) {
            $file_id = $user_state['file_id'];
            $movies[$text] = $file_id;
            saveData($db_file, $movies);
            unset($states[$user_id]);
            saveData($state_file, $states);
            bot('sendMessage', ['chat_id' => $user_id, 'text' => "🎉 <b>Kino saqlandi!</b> Kod: <code>{$text}</code>", 'parse_mode' => 'HTML', 'reply_markup' => getAdminKeyboard()], $token);
            exit();
        }

        if ($step == 'waiting_for_del_code' && $text) {
            if (isset($movies[$text])) {
                unset($movies[$text]);
                saveData($db_file, $movies);
                bot('sendMessage', ['chat_id' => $user_id, 'text' => "✅ Kod: <code>{$text}</code> bo'lgan kino o'chirildi.", 'parse_mode' => 'HTML', 'reply_markup' => getAdminKeyboard()], $token);
            } else {
                bot('sendMessage', ['chat_id' => $user_id, 'text' => "❌ Bunday kodli kino topilmadi.", 'reply_markup' => getAdminKeyboard()], $token);
            }
            unset($states[$user_id]);
            saveData($state_file, $states);
            exit();
        }

        if ($step == 'waiting_for_ch_id' && $text) {
            $states[$user_id] = ['step' => 'waiting_for_ch_name', 'ch_id' => $text];
            saveData($state_file, $states);
            bot('sendMessage', ['chat_id' => $user_id, 'text' => "✅ ID qabul qilindi. Endi kanal tugmasi uchun <b>nom</b> yuboring:", 'parse_mode' => 'HTML'], $token);
            exit();
        }

        if ($step == 'waiting_for_ch_name' && $text) {
            $states[$user_id]['step'] = 'waiting_for_ch_url';
            $states[$user_id]['ch_name'] = $text;
            saveData($state_file, $states);
            bot('sendMessage', ['chat_id' => $user_id, 'text' => "✅ Nom qabul qilindi. Endi kanal <b>taklif havolasini (link)</b> yuboring:", 'parse_mode' => 'HTML'], $token);
            exit();
        }

        if ($step == 'waiting_for_ch_url' && $text) {
            $channels[] = [
                'id' => (int)$user_state['ch_id'],
                'name' => $user_state['ch_name'],
                'url' => $text
            ];
            saveData($channels_file, $channels);
            unset($states[$user_id]);
            saveData($state_file, $states);
            bot('sendMessage', ['chat_id' => $user_id, 'text' => "🎉 <b>Kanal muvaffaqiyatli qo'shildi!</b>", 'parse_mode' => 'HTML', 'reply_markup' => getAdminKeyboard()], $token);
            exit();
        }
        
        if ($step == 'waiting_for_del_ch_id' && $text) {
            $channels = array_filter($channels, function($ch) use ($text) {
                return $ch['id'] != $text;
            });
            $channels = array_values($channels);
            saveData($channels_file, $channels);
            unset($states[$user_id]);
            saveData($state_file, $states);
            bot('sendMessage', ['chat_id' => $user_id, 'text' => "✅ Kanal o'chirib tashlandi.", 'reply_markup' => getAdminKeyboard()], $token);
            exit();
        }
    }

    // JONLI TEKSHIRUV
    if (!checkSubscription($user_id, $channels, $token)) {
        bot('sendMessage', [
            'chat_id' => $user_id, 
            'text' => "⚠️ Botdan foydalanish uchun avval quyidagi kanalimizga obuna bo'ling:", 
            'reply_markup' => getSubscribeKeyboard($channels)
        ], $token);
        exit();
    }

    if ($text) {
        if (isset($movies[$text])) {
            bot('sendVideo', [
                'chat_id' => $user_id,
                'video' => $movies[$text],
                'caption' => "🎬 Siz izlagan kino (Kod: {$text})"
            ], $token);
        } else {
            bot('sendMessage', ['chat_id' => $user_id, 'text' => "❌ Bunday kodli kino topilmadi."], $token);
        }
    }
}
?>
