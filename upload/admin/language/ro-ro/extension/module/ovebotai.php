<?php
// Heading
$_['heading_title']            = 'Ovebot - Chatbot AI, Live Chat &amp; Agent AI de Vânzări';

// Text — generic
$_['text_extension']           = 'Extensii';
$_['text_success']             = 'Succes: Ai modificat modulul Ovebot.ai!';
$_['text_home']                = 'Acasă';

// Setup wizard — step labels
$_['text_step_connect']             = 'Conectează contul';
$_['text_step_pages']               = 'Pagini site';
$_['text_step_products']            = 'Produse';
$_['text_step_golive']              = 'Publică';

// Setup wizard — step 1 (connect)
$_['text_connect_heading']    = 'Conectează magazinul la Ovebot.ai';
$_['text_connect_lead']       = 'Autentifică-te în contul tău Ovebot.ai și permite acestui site să se sincronizeze cu el. Vei fi trimis pe ovebot.ai pentru a te autentifica, apoi adus direct înapoi aici pentru a finaliza configurarea agentului tău AI de chat.';
$_['button_connect_existing']  = 'Conectează-te cu un cont existent &rarr;';
$_['button_try_free']          = 'Încearcă gratuit &rarr;';

// Setup wizard — step 2 (pages)
$_['text_pages_heading']      = 'Învață agentul AI de chat despre site-ul tău';
$_['text_pages_lead']         = 'Selectează paginile de mai jos (ex. Despre noi, Întrebări frecvente, Livrare &amp; Retur). Agentul tău AI le va citi și va folosi aceste informații pentru a răspunde corect întrebărilor clienților, direct în chat-ul de pe site.';
$_['text_no_pages']            = 'Nu au fost găsite pagini de informații active.';

// Setup wizard — step 3 (products)
$_['text_products_heading']   = 'Învață agentul AI de chat despre produsele tale';
$_['text_products_checking']  = 'Se verifică câte produse pot fi trimise agentului tău AI&hellip;';
$_['text_no_products']         = 'Nu au fost găsite produse active - agentul tău AI nu va avea încă produse de recomandat.';
$_['text_products_indexed']    = 'produse vor fi trimise agentului tău AI, ca să le poată recomanda clienților.';

// Setup wizard — step 4 (go live)
$_['text_golive_heading']     = 'Gata de publicare';
$_['text_golive_lead']        = 'Totul este pregătit. Apasă mai jos pentru a trimite paginile și produsele selectate către Ovebot.ai - agentul tău AI de chat le va folosi imediat.';
$_['text_syncing']            = 'Se sincronizează cu Ovebot.ai&hellip;';
$_['text_done_heading']       = 'Gata!';
$_['text_done_lead']          = 'Magazinul tău este acum conectat la Ovebot.ai. Agentul tău AI este activ pe site chiar acum, gata să discute cu clienții, să recomande produse și să răspundă la întrebări despre statusul comenzilor.';

// Setup wizard — buttons
$_['button_settings']          = 'Mergi la setări';
$_['button_chat']              = 'Discută cu agentul AI &rarr;';
$_['button_next']              = 'Înainte &rarr;';
$_['button_prev']              = '&larr; Înapoi';
$_['button_finish']            = 'Finalizează configurarea &rarr;';
$_['button_retry']             = 'Reîncearcă';

// Setup wizard — messages
$_['text_setup_complete']      = 'Configurare finalizată!';
$_['text_error']               = 'A apărut o eroare. Te rugăm să încerci din nou.';

// Dashboard
$_['text_dashboard_heading']        = 'Ovebot.ai';
$_['text_dashboard_lead']           = 'Agentul tău AI de chat este activ. Gestionează setările sau deschide chat-ul mai jos.';
$_['text_connected']           = 'Conectat';
$_['button_disconnect']        = 'Deconectează';
$_['button_account']           = 'Cont Ovebot.ai';
$_['text_confirm_disconnect']  = 'Deconectezi acest magazin de la Ovebot.ai? Agentul AI de chat nu va mai funcționa până la reconectare.';

// Dashboard — produse indexate
$_['text_products_indexed_label']   = 'Produse indexate de agentul AI';

// Dashboard — baze de cunoștințe
$_['text_kb_heading']          = 'Baze de cunoștințe';
$_['text_kb_intro']            = 'Acestea sunt resursele pe care agentul tău AI le folosește pentru a răspunde la întrebările clienților în chat. Editarea uneia dintre aceste pagini în OpenCart actualizează automat intrarea de aici în câteva minute - sau folosește „Editează pe Ovebot.ai" mai jos pentru a o modifica direct.';
$_['text_kb_active_count']     = '%d din %d intrări active';
$_['text_kb_none']             = 'Nu există încă intrări în baza de cunoștințe - %s.';
$_['text_kb_add_one']          = 'adaugă una aici';
$_['text_kb_error']            = 'Nu s-au putut încărca intrările din baza de cunoștințe de la Ovebot.ai.';
$_['button_edit_on_ovebotai']  = 'Editează pe Ovebot.ai';
$_['text_kb_active']           = 'Activ';
$_['text_kb_inactive']         = 'Inactiv';

// Settings
$_['text_settings_heading']         = 'Setări';
$_['text_back']                = 'Panou de control';

// Settings — chat widget
$_['text_widget_heading']      = 'Chat pe site';
$_['entry_chat_status']        = 'Afișează bula de chat pe site-ul tău';
$_['text_enabled']             = 'Activat';
$_['text_disabled']            = 'Dezactivat';
$_['help_chat_status']         = 'Când e activată, bula de chat a agentului AI apare pe fiecare pagină a site-ului, astfel încât clienții pot începe o conversație.';

// Settings — product feed
$_['text_feed_heading']        = 'Feed de produse';
$_['help_feed_intro']          = 'Ovebot.ai citește periodic acest URL pentru a menține recomandările de produse ale agentului AI la zi cu catalogul tău (stoc, preț, disponibilitate).';
$_['entry_feed_url']           = 'URL feed';
$_['button_copy']              = 'Copiază';
$_['button_regen_hash']        = 'Regenerează codul';
$_['button_clear_cache']       = 'Golește cache-ul feed-ului';
$_['help_clear_cache']         = 'Feed-ul este pus în cache pentru performanță. Golește-l după modificări majore ale catalogului.';

// Settings — order tracking
$_['text_orders_heading']      = 'API urmărire comenzi';
$_['help_orders_intro']        = 'Ovebot.ai apelează acest endpoint, autentificat cu datele de mai jos, astfel încât agentul AI poate răspunde la întrebări de tip „Unde este comanda mea?" cu informații live de urmărire.';
$_['entry_order_url']          = 'URL endpoint';
$_['entry_api_user']           = 'Utilizator API';
$_['entry_api_pass']           = 'Parolă API';
$_['button_regen_creds']       = 'Regenerează credențialele';
$_['help_regen_creds']         = 'Generează o nouă pereche utilizator/parolă. Cele vechi vor înceta să funcționeze imediat.';

// Settings — delivery estimate
$_['text_delivery_heading']    = 'Estimare livrare';
$_['help_delivery_intro']      = 'Agentul tău AI folosește aceste intervale pentru a răspunde la „Când ajunge comanda mea?" - numărate în zile lucrătoare, fără duminici. Setează un interval min-max realist pentru fiecare dintre cele trei situații de mai jos.';
$_['entry_delivery_shipped']   = 'Comenzi care au fost deja expediate';
$_['help_delivery_shipped']    = 'Numărate din ziua expedierii comenzii, până ajunge la client.';
$_['entry_delivery_instock']   = 'Comenzi noi - toate produsele în stoc';
$_['help_delivery_instock']    = 'Numărate din ziua plasării comenzii - nimic de așteptat înainte de expediere.';
$_['entry_delivery_oos']       = 'Comenzi noi - cel puțin un produs fără stoc';
$_['help_delivery_oos']        = 'Numărate din ziua plasării comenzii - include așteptarea reaprovizionării înainte de expediere.';
$_['text_business_days']       = 'zile lucrătoare';

// Settings — appearance
$_['text_appearance_heading']       = 'Aspect';
$_['button_configure_appearance']   = 'Configurează aspectul';
$_['text_look_feel']                = 'Aspect &amp; stil';
$_['entry_accent_color']            = 'Culoare accent';
$_['entry_theme']                   = 'Temă';
$_['text_theme_default']            = 'Implicit (deschis)';
$_['text_theme_light']              = 'Deschis';
$_['text_theme_dark']               = 'Închis';
$_['entry_widget_language']         = 'Limbă';
$_['text_lang_default']             = 'Implicit';
$_['text_lang_auto']                = 'Automat (browser)';
$_['entry_audio_beep']              = 'Semnal audio';
$_['text_audio_default']            = 'Implicit (redă)';
$_['text_audio_play']               = 'Redă';
$_['text_audio_none']               = 'Fără';
$_['text_position_heading']         = 'Poziție pe pagină';
$_['entry_widget_position']         = 'Poziția widget-ului';
$_['text_position_default']         = 'Implicit (dreapta)';
$_['text_position_right']           = 'Jos dreapta';
$_['text_position_left']            = 'Jos stânga';
$_['text_px_offset']                = 'px distanță de jos';
$_['help_widget_position']          = 'Mărește distanța pentru a poziționa widget-ul deasupra altui buton plutitor, ex. WhatsApp.';
$_['text_messages_heading']         = 'Mesaje';
$_['entry_subtitle']                = 'Subtitlu';
$_['text_subtitle_placeholder']     = 'ex. De obicei răspunde în câteva minute';
$_['help_subtitle']                 = 'Afișat sub numele asistentului în antetul chat-ului.';
$_['entry_proactive_message']       = 'Mesaj proactiv';
$_['text_proactive_placeholder']    = 'ex. Ai nevoie de ajutor să găsești ceva?';
$_['text_seconds_after_load']       = 's după încărcarea paginii';
$_['help_proactive_message']        = 'Un mesaj scurt care apare singur pentru a invita vizitatorii la chat. Lasă gol pentru a dezactiva.';

// Settings — save + AJAX messages
$_['button_save_settings']          = 'Salvează setările';
$_['text_settings_saved']           = 'Setări salvate.';
$_['text_settings_saved_reconnect'] = 'Setările de chat au fost salvate. Reconectează-te la Ovebot.ai pentru a sincroniza setările de feed și comenzi.';
$_['text_settings_sync_failed']     = 'Setările au fost salvate local, dar nu s-au putut sincroniza cu Ovebot.ai.';
$_['text_feed_regenerated']         = 'URL-ul feed-ului a fost regenerat și sincronizat cu Ovebot.ai.';
$_['text_feed_regen_failed']        = 'Nu s-a putut sincroniza cu Ovebot.ai - codul feed-ului a rămas neschimbat.';
$_['text_creds_regenerated']        = 'Credențialele au fost regenerate și sincronizate cu Ovebot.ai.';
$_['text_creds_regen_failed']       = 'Nu s-a putut sincroniza cu Ovebot.ai - credențialele au rămas neschimbate.';
$_['text_cache_cleared']            = 'Cache golit.';
$_['text_saving']                   = 'Se salvează…';
$_['text_confirm_regen_hash']       = 'Regenerezi codul feed-ului? URL-ul curent al feed-ului va înceta să funcționeze.';
$_['text_confirm_regen_creds']      = 'Regenerezi credențialele API? Cele curente vor înceta să funcționeze imediat.';
$_['text_confirm_clear_cache']      = 'Golești cache-ul feed-ului de produse?';
$_['text_copied']                   = 'Copiat!';

// Errors
$_['error_permission']         = 'Atenție: Nu ai permisiunea de a modifica modulul Ovebot.ai!';
$_['error_sync_setup']         = 'Nu s-au putut sincroniza setările de feed și comenzi cu Ovebot.ai.';
