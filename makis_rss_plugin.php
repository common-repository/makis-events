<?php
/*
Plugin Name: Makis Events
Plugin URI: https://makis.world/wiki/plugins/wp
Description: Makis Events and Entertainment for Your Homepage (automated content)
Version: 1.0
Author: Makis Community
Author URI: https://makis.world
*/
?>
<?php
/*  Copyright 2018  Makis Team  (email: info@makis.world)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
?>
<?php

include_once(ABSPATH . WPINC . '/class-simplepie.php');

define('makis_rss_url','https://makis.world/feed/common.rss');

// here's the function we'd like to call with our cron job
function makisrss_load_posts_function()
{
    global $wpdb;

    $channelsstr = "SELECT * FROM $wpdb->prefix" . 'makis_rss_channel' . " WHERE active = true";

    $channels = $wpdb->get_results($channelsstr);
    foreach ($channels as $channel) {
        echo "Channel: " . $channel->name;

        $url = makis_rss_url;
        $url .= "?category=".$channel->category_id;
        if($channel->city_id){
            $url .= "&city=".$channel->city_id;
        }
        echo "RSS FROM: " . $url;
        $feed = new SimplePie();
        $feed->set_feed_url($url);
        $success = $feed->init();
        if (!$success) {
            $feed->force_fsockopen(true);
            $feed->init();
        }
        echo "RSS CONNECT " . $success;
        echo "<br/>";

        $feed->handle_content_type();

        foreach ($feed->get_items() as $item) {
            $post_item["id"] = $item->get_id();
            $post_item["source"] = "Makis";
            $post_item["date"] = $item->get_date('d.m.Y - H:i');
            $post_item["title"] = $item->get_title();
            $post_item["description"] = $item->get_description();
            $post_item["link"] = $item->get_permalink();
            $post_item["publiclink"] = $item->get_permalink();
            $post_item["publiclinkfb"] = $item->get_permalink();
            $post_item["content"] = $item->get_content();
            $post_item["category"] = $channel->wp_category_id;
            $post_item["timestamp"] = $item->get_date('U');

            if ($return = $item->get_item_tags(SIMPLEPIE_NAMESPACE_MEDIARSS, 'content')) {
                $post_item['thumbnail'] = $return[0]['attribs']['']['url'];
                $post_item['picture'] = $return[0]['attribs']['']['url'];
            } else {
                $post_item['thumbnail'] = null;
                $post_item['picture'] = null;
            }
            echo "Item: " . $post_item["link"];

            if (!makisrss_check_post_exists($post_item["id"])) {
                echo " save to DB";

                $id = makisrss_post_to_wp($post_item);

                if ($id) {
                    makisrss_insert_post($post_item);
                }
            } else echo " exists yet ";

            echo "<br/>";
        }
    }
    echo "done";
}

function makisrss_check_post_exists($uid)
{
    global $wpdb;
    $post_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->prefix" . 'makis_rss_post' . " WHERE uid = %s", $uid));
    if ($post_count == 0) return false; else return true;
}


function makisrss_insert_post($post_item)
{
    global $wpdb;
    $success = $wpdb->insert(
        $wpdb->prefix . 'makis_rss_post',
        array(
            'uid' => $post_item["id"],
            'head' => $post_item["title"],
            'create_time' => current_time('mysql')
        )
    );
}

function makisrss_post_to_wp($makis_post)
{
    $tags = explode(" ", $makis_post["title"]);
    $alltags = array();
    foreach ($tags as $tag) {
        $tag = str_replace("&uuml;", "ü", $tag);
        $tag = str_replace("&auml;", "ä", $tag);
        $tag = str_replace("&ouml;", "ö", $tag);
        $tag = str_replace("&Uuml;", "Ü", $tag);
        $tag = str_replace("&Auml;", "Ä", $tag);
        $tag = str_replace("&Ouml;", "Ö", $tag);
        $tag = str_replace("&szlig;", "ß", $tag);
        $tag = str_replace("&#128;", "€", $tag);
        $tag = str_replace("&amp;", "&", $tag);
        $tagstatus = makisrss_remove_tag_filling($tag);
        $toremove = array(',', '.', ':', '/', '!', '-', '&', '-', '*', '|', '\'', '´', '`', '+', '%', '★', '_', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '€', '(', ')', '[update]');
        if (!$tagstatus) array_push($alltags, str_replace($toremove, "", trim(strtolower($tag))));
    }
    array_push($alltags, $makis_post["source"]);

    // poste artikel
    $my_post = array();
    $my_post['post_title'] = $makis_post["title"];
    $my_post['post_content'] = $makis_post["content"];
    $my_post['post_status'] = 'publish';
    $my_post['post_post_type'] = 'post';
    $my_post['tags_input'] = $alltags;
    $my_post['post_author'] = 1;
    $my_post['post_category'] = array($makis_post["category"]);

    // Insert the post into the database
    $id = wp_insert_post($my_post);
    //setze thumbnail
    if ($id) {
        makisrss_somatic_attach_external_image($makis_post["picture"], $id, true);
        return $id;
    }
    return false;
}

function makisrss_remove_tag_filling($tag)
{
    $toremove = array('aber', 'abermals', 'abgerufen', 'abgerufene', 'abgerufener', 'abgerufenes', 'ähnlich', 'alle', 'allein', 'allem', 'allemal', 'allen', 'allenfalls', 'allenthalben', 'aller', 'allerdings', 'allerlei', 'alles', 'allesamt', 'allgemein', 'allmählich', 'allzu', 'als', 'alsbald', 'also', 'alt', 'am', 'an', 'andauernd', 'andere', 'anderem', 'anderen', 'anderer', 'andererseits', 'anderes', 'andern', 'andernfalls', 'anders', 'anerkannt', 'anerkannte', 'anerkannter', 'anerkanntes', 'angesetzt', 'angesetzte', 'angesetzter', 'anscheinend', 'anstatt', 'auch', 'auf', 'auffallend', 'aufgrund', 'aufs', 'augenscheinlich', 'aus', 'ausdrücklich', 'ausdrückt', 'ausdrückte', 'ausgedrückt', 'ausgenommen', 'ausgerechnet', 'ausnahmslos', 'außen', 'außer', 'außerdem', 'außerhalb', 'äußerst', 'bald', 'bei', 'beide', 'beiden', 'beiderlei', 'beides', 'beim', 'beinahe', 'bekannt', 'bekannte', 'bekannter', 'bekanntlich', 'bereits', 'besonders', 'besser', 'bestenfalls', 'bestimmt', 'beträchtlich', 'bevor', 'bezüglich', 'bin', 'bis', 'bisher', 'bislang', 'bist', 'bloß', 'Bsp', 'bzw', 'ca', 'Co', 'da', 'dabei', 'dadurch', 'dafür', 'dagegen', 'daher', 'dahin', 'damals', 'damit', 'danach', 'daneben', 'dank', 'danke', 'dann', 'dannen', 'daran', 'darauf', 'daraus', 'darf', 'darfst', 'darin', 'darüber', 'darum', 'darunter', 'das', 'dass', 'dasselbe', 'davon', 'davor', 'dazu', 'dein', 'deine', 'deinem', 'deinen', 'deiner', 'deines', 'dem', 'demgegenüber', 'demgemäß', 'demnach', 'demselben', 'den', 'denen', 'denkbar', 'denn', 'dennoch', 'denselben', 'der', 'derart', 'derartig', 'deren', 'derer', 'derjenige', 'derjenigen', 'derselbe', 'derselben', 'derzeit', 'des', 'deshalb', 'desselben', 'dessen', 'desto', 'deswegen', 'dich', 'die', 'diejenige', 'dies', 'diese', 'dieselbe', 'dieselben', 'diesem', 'diesen', 'dieser', 'dieses', 'diesmal', 'diesseits', 'dir', 'direkt', 'direkte', 'direkten', 'direkter', 'doch', 'dort', 'dorther', 'dorthin', 'drin', 'drüber', 'drunter', 'du', 'dunklen', 'durch', 'durchaus', 'durchweg', 'eben', 'ebenfalls', 'ebenso', 'ehe', 'eher', 'eigenen', 'eigenes', 'eigentlich', 'ein', 'eine', 'einem', 'einen', 'einer', 'einerseits', 'eines', 'einfach', 'einig', 'einige', 'einigem', 'einigen', 'einiger', 'einigermaßen', 'einiges', 'einmal', 'einseitig', 'einseitige', 'einseitigen', 'einseitiger', 'einst', 'einstmals', 'einzig', 'e. K.', 'entsprechend', 'entweder', 'er', 'ergo', 'erhält', 'erheblich', 'erneut', 'erst', 'ersten', 'es', 'etc', 'etliche', 'etwa', 'etwas', 'euch', 'euer', 'eure', 'eurem', 'euren', 'eurer', 'eures', 'falls', 'fast', 'ferner', 'folgende  ', 'folgenden', 'folgender', 'folgendermaßen', 'folgendes', 'folglich', 'förmlich', 'fortwährend', 'fraglos', 'Frau', 'frei', 'freie', 'freies', 'freilich', 'für', 'gab', 'gängig', 'gängige', 'gängigen', 'gängiger', 'gängiges', 'ganz', 'ganze', 'ganzem', 'ganzen', 'ganzer', 'ganzes', 'gänzlich', 'gar', 'GbR', 'GbdR', 'geehrte', 'geehrten', 'geehrter', 'gefälligst', 'gegen', 'gehabt', 'gekonnt', 'gelegentlich', 'gemacht', 'gemäß', 'gemeinhin', 'gemocht', 'genau', 'genommen', 'genug', 'geradezu', 'gern', 'gestern', 'gestrige', 'getan', 'geteilt', 'geteilte', 'getragen', 'gewesen', 'gewiss', 'gewisse', 'gewissermaßen', 'gewollt', 'geworden', 'ggf', 'gib', 'gibt', 'gleich', 'gleichsam', 'gleichwohl', 'gleichzeitig', 'glücklicherweise', 'GmbH', 'Gott sei Dank', 'größtenteils', 'Grunde', 'gute', 'guten', 'hab', 'habe', 'halb', 'hallo', 'halt', 'hast', 'hat', 'hatte', 'hätte', 'hätte', 'hätten', 'hattest', 'hattet', 'häufig', 'heraus', 'herein', 'heute', 'heutige', 'hier', 'hiermit', 'hiesige', 'hin', 'hinein', 'hingegen', 'hinlänglich', 'hinten', 'hinter', 'hinterher', 'hoch', 'höchst', 'höchstens', 'ich', 'ihm', 'ihn', 'ihnen', 'ihr', 'ihre', 'ihrem', 'ihren', 'ihrer', 'ihres', 'im', 'immer', 'immerhin', 'immerzu', 'in', 'indem', 'indessen', 'infolge', 'infolgedessen', 'innen', 'innerhalb', 'ins', 'insbesondere', 'insofern', 'insofern', 'inzwischen', 'irgend', 'irgendein', 'irgendeine', 'irgendjemand', 'irgendwann', 'irgendwas', 'irgendwen', 'irgendwer', 'irgendwie', 'irgendwo', 'ist', 'ja', 'jährig', 'jährige', 'jährigen', 'jähriges', 'je', 'jede', 'jedem', 'jeden', 'jedenfalls', 'jeder', 'jederlei', 'jedes', 'jedoch', 'jemals', 'jemand', 'jene', 'jenem', 'jenen', 'jener', 'jenes', 'jenseits', 'jetzt', 'kam', 'kann', 'kannst', 'kaum', 'kein', 'keine', 'keinem', 'keinen', 'keiner', 'keinerlei', 'keines', 'keines', 'keinesfalls', 'keineswegs', 'KG', 'klar', 'klare', 'klaren', 'klares', 'klein', 'kleinen', 'kleiner', 'kleines', 'konkret', 'konkrete', 'konkreten', 'konkreter', 'konkretes', 'können', 'könnt', 'konnte', 'könnte', 'konnten', 'könnten', 'künftig', 'lag', 'lagen', 'langsam', 'längst', 'längstens', 'lassen', 'laut', 'lediglich', 'leer', 'leicht', 'leider', 'lesen', 'letzten', 'letztendlich', 'letztens', 'letztes', 'letztlich', 'lichten', 'links', 'Ltd', 'mag', 'magst', 'mal', 'man', 'manche', 'manchem', 'manchen', 'mancher', 'mancherorts', 'manches', 'manchmal', 'Mann', 'mehr', 'mehrere', 'mehrfach', 'mein', 'meine', 'meinem', 'meinen', 'meiner', 'meines', 'meinetwegen', 'meist', 'meiste', 'meisten', 'meistens', 'meistenteils', 'meta', 'mich', 'mindestens', 'mir', 'mit', 'mithin', 'mitunter', 'möglich', 'mögliche', 'möglichen', 'möglicher', 'möglicherweise', 'möglichst', 'morgen', 'morgige', 'muss', 'müssen', 'musst', 'müsst', 'musste', 'müsste', 'müssten', 'nach', 'nachdem', 'nachher', 'nachhinein', 'nächste', 'nämlich', 'naturgemäß', 'natürlich', 'neben', 'nebenan', 'nebenbei', 'nein', 'neu', 'neue', 'neuem', 'neuen', 'neuer', 'neuerdings', 'neuerlich', 'neues', 'neulich', 'nicht', 'nichts', 'nichtsdestotrotz', 'nichtsdestoweniger', 'nie', 'niemals', 'niemand', 'nimm', 'nimmer', 'nimmt', 'nirgends', 'nirgendwo', 'noch', 'nötigenfalls', 'nun', 'nunmehr', 'nur', 'ob', 'oben', 'oberhalb', 'obgleich', 'obschon', 'obwohl', 'oder', 'offenbar', 'offenkundig', 'offensichtlich', 'oft', 'ohne', 'ohnedies', 'OHG', 'OK', 'partout', 'per', 'persönlich', 'pfui', 'plötzlich', 'praktisch', 'pro', 'quasi', 'recht', 'rechts', 'regelmäßig', 'reichlich', 'relativ', 'restlos', 'richtiggehend', 'riesig', 'rund', 'rundheraus', 'rundum', 'samt', 'sämtliche', 'sattsam', 'schätzen', 'schätzt', 'schätzte', 'schätzten', 'schlechter', 'schlicht', 'schlichtweg', 'schließlich', 'schlussendlich', 'schnell', 'schon', 'Schreibens', 'Schreiber', 'schwerlich', 'schwierig', 'sehr', 'sei', 'seid', 'sein', 'seine', 'seinem', 'seinen', 'seiner', 'seines', 'seit', 'seitdem', 'Seite', 'Seiten', 'seither', 'selber', 'selbst', 'selbstredend', 'selbstverständlich', 'selten', 'seltsamerweise', 'sich', 'sicher', 'sicherlich', 'sie', 'siehe', 'sieht', 'sind', 'so', 'sobald', 'sodass', 'soeben', 'sofern', 'sofort', 'sog', 'sogar', 'solange', 'solch', 'solche', 'solchem', 'solchen', 'solcher', 'solches', 'soll', 'sollen', 'sollst', 'sollt', 'sollte', 'sollten', 'solltest', 'somit', 'sondern', 'sonders', 'sonst', 'sooft', 'soviel', 'soweit', 'sowie', 'sowieso', 'sowohl', 'sozusagen', 'später', 'spielen', 'startet', 'startete', 'starteten', 'statt', 'stattdessen', 'steht', 'stellenweise', 'stets', 'Tages', 'tat', 'tatsächlich', 'tatsächlichen', 'tatsächlicher', 'tatsächliches', 'teile', 'total', 'trotzdem', 'übel', 'über', 'überall', 'überallhin', 'überaus', 'überdies', 'überhaupt', 'üblicher', 'übrig', 'übrigens', 'um', 'umso', 'umständehalber', 'unbedingt', 'unbeschreiblich', 'und', 'unerhört', 'ungefähr', 'ungemein', 'ungewöhnlich', 'ungleich', 'unglücklicherweise', 'unlängst', 'unmaßgeblich', 'unmöglich', 'unmögliche', 'unmöglichen', 'unmöglicher', 'unnötig', 'uns', 'unsagbar', 'unsäglich', 'unser', 'unsere', 'unserem', 'unseren', 'unserer', 'unseres', 'unserm', 'unstreitig', 'unten', 'unter', 'unterbrach', 'unterbrechen', 'unterhalb', 'unwichtig', 'unzweifelhaft', 'usw', 'vergleichsweise', 'vermutlich', 'veröffentlichen', 'veröffentlicht', 'veröffentlichte', 'veröffentlichten', 'veröffentlichtes', 'viel', 'viele', 'vielen', 'vieler', 'vieles', 'vielfach', 'vielleicht', 'vielmals', 'voll', 'vollends', 'völlig', 'vollkommen', 'vollständig', 'vom', 'von', 'vor', 'voran', 'vorbei', 'vorgestern', 'vorher', 'vorne', 'vorüber', 'während', 'währenddessen', 'wahrscheinlich', 'wann', 'war', 'wäre', 'waren', 'wären', 'warst', 'warum', 'was', 'weder', 'weg', 'wegen', 'weidlich', 'weil', 'Weise', 'weiß', 'weitem', 'weiter', 'weitere', 'weiterem', 'weiteren', 'weiterer', 'weiteres', 'weiterhin', 'weitgehend', 'welche', 'welchem', 'welchen', 'welcher', 'welches', 'wem', 'wen', 'wenig', 'wenige', 'weniger', 'wenigstens', 'wenn', 'wenngleich', 'wer', 'werde', 'werden', 'werdet', 'weshalb', 'wessen', 'wichtig', 'wie', 'wieder', 'wiederum', 'wieso', 'wiewohl', 'will', 'willst', 'wir', 'wird', 'wirklich', 'wirst', 'wo', 'wodurch', 'wogegen', 'woher', 'wohin', 'wohingegen', 'wohl', 'wohlgemerkt', 'wohlweislich', 'wollen', 'wollt', 'wollte', 'wollten', 'wolltest', 'wolltet', 'womit', 'womöglich', 'woraufhin', 'woraus', 'worin', 'wurde', 'würde', 'würden', 'z. B.', 'zahlreich', 'zeitweise', 'ziemlich', 'zu', 'zudem', 'zuerst', 'zufolge', 'zugegeben', 'zugleich', 'zuletzt', 'zum', 'zumal', 'zumeist', 'zur', 'zurück', 'zusammen', 'zusehends', 'zuvor', 'zuweilen', 'zwar', 'zweifellos', 'zweifelsfrei', 'zweifelsohne', 'zwischen', 'doku', 'Doku', 'dokumentation', 'Dokumentation', 'reportage', 'Reportage', '★', ' HD', '-', '"', '|', '2010', '2011', '2012', '2013', '2014', '2015', '2016', '2017', '2018', '2019', '2020', 'eur', 'euro', 'woche', 'wochenende', 'wochen', 'tag', 'tage', '€');

    return in_array(trim(strtolower($tag)), $toremove);
}

/**
 * Download an image from the specified URL and attach it to a post.
 * Modified version of core function media_sideload_image() in /wp-admin/includes/media.php  (which returns an html img tag instead of attachment ID)
 * Additional functionality: ability override actual filename, and to pass $post_data to override values in wp_insert_attachment (original only allowed $desc)
 *
 * @since 1.4 Somatic Framework
 *
 * @param string $url (required) The URL of the image to download
 * @param int $post_id (required) The post ID the media is to be associated with
 * @param bool $thumb (optional) Whether to make this attachment the Featured Image for the post (post_thumbnail)
 * @param string $filename (optional) Replacement filename for the URL filename (do not include extension)
 * @param array $post_data (optional) Array of key => values for wp_posts table (ex: 'post_title' => 'foobar', 'post_status' => 'draft')
 * @return int|object The ID of the attachment or a WP_Error on failure
 */
function makisrss_somatic_attach_external_image($url = null, $post_id = null, $thumb = null, $filename = null, $post_data = array())
{
    if (!$url || !$post_id) return new WP_Error('missing', "Need a valid URL and post ID...");
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    // Download file to temp location, returns full server path to temp file, ex; /home/user/public_html/mysite/wp-content/26192277_640.tmp
    $tmp = download_url($url);

    $file_array = array();
    // If error storing temporarily, unlink
    if (is_wp_error($tmp)) {
        @unlink($file_array['tmp_name']);   // clean up
        $file_array['tmp_name'] = '';
        return $tmp; // output wp_error
    }

    preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches);    // fix file filename for query strings
    $url_filename = basename($matches[0]);                                                  // extract filename from url for title
    $url_type = wp_check_filetype($url_filename);                                           // determine file type (ext and mime/type)

    // override filename if given, reconstruct server path
    if (!empty($filename)) {
        $filename = sanitize_file_name($filename);
        $tmppath = pathinfo($tmp);                                                        // extract path parts
        $new = $tmppath['dirname'] . "/" . $filename . "." . $tmppath['extension'];          // build new path
        rename($tmp, $new);                                                                 // renames temp file on server
        $tmp = $new;                                                                        // push new filename (in path) to be used in file array later
    }

    // assemble file data (should be built like $_FILES since wp_handle_sideload() will be using)
    $file_array['tmp_name'] = $tmp;                                                         // full server path to temp file

    if (!empty($filename)) {
        $file_array['name'] = $filename . "." . $url_type['ext'];                           // user given filename for title, add original URL extension
    } else {
        $file_array['name'] = $url_filename;                                                // just use original URL filename
    }

    // set additional wp_posts columns
    if (empty($post_data['post_title'])) {
        $post_data['post_title'] = basename($url_filename, "." . $url_type['ext']);         // just use the original filename (no extension)
    }

    // make sure gets tied to parent
    if (empty($post_data['post_parent'])) {
        $post_data['post_parent'] = $post_id;
    }

    // required libraries for media_handle_sideload
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // do the validation and storage stuff
    $att_id = media_handle_sideload($file_array, $post_id, null, $post_data);             // $post_data can override the items saved to wp_posts table, like post_mime_type, guid, post_parent, post_title, post_content, post_status

    // If error storing permanently, unlink
    if (is_wp_error($att_id)) {
        @unlink($file_array['tmp_name']);   // clean up
        return $att_id; // output wp_error
    }

    // set as post thumbnail if desired
    if ($thumb) {
        set_post_thumbnail($post_id, $att_id);
    }

    return $att_id;
}

global $makisrss_db_version;
$makisrss_db_version = '1.0.1';

function makisrss_db_install()
{
    global $wpdb;
    global $makisrss_db_version;

    $table_name = $wpdb->prefix . 'makis_rss_channel';
    $table_name_posts = $wpdb->prefix . 'makis_rss_post';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = array();
    if ($wpdb->get_var("SHOW TABLES LIKE '" . $table_name . "'") !== $table_name) {
        echo "Try create makis channel table";
        $sql[] = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name tinytext NOT NULL,
            category_id mediumint(9) NOT NULL,
            city_id mediumint(9) DEFAULT NULL,
            wp_category_id mediumint(9) NOT NULL,
            active BOOLEAN DEFAULT TRUE,
            PRIMARY KEY  (id)
        ) $charset_collate;";
    }

    if ($wpdb->get_var("SHOW TABLES LIKE '" . $table_name_posts . "'") !== $table_name_posts) {
        echo "Try create makis post table";
        $sql[] = "CREATE TABLE $table_name_posts (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            uid varchar(255) NOT NULL,
            head varchar(255) NOT NULL,
            create_time datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY makis_rss_uid_key (uid)
        ) $charset_collate;";
    }

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta($sql);

    add_option('makisrss_db_version', $makisrss_db_version);
}

register_activation_hook(__FILE__, 'makisrss_db_install');

// add custom interval
function makisrss_cron_add_minute($schedules)
{
    // Adds once every minute to the existing schedules.
    $schedules['every30minutes'] = array(
        'interval' => 1800,
        'display' => __('Every 30 Minutes')
    );
    return $schedules;
}

add_filter('cron_schedules', 'makisrss_cron_add_minute');

// create a scheduled event (if it does not exist already)
function makisrss_cronstarter_activation()
{
    if (!wp_next_scheduled('MakisRssCronjob')) {
//        wp_schedule_event( time(), 'daily', 'MakisRssCronjob' );
        wp_schedule_event(time(), 'every30minutes', 'MakisRssCronjob');
    }
}

// and make sure it's called whenever WordPress loads
add_action('wp', 'makisrss_cronstarter_activation');

// unschedule event upon plugin deactivation
function makisrss_cronstarter_deactivate()
{
    // find out when the last event was scheduled
    $timestamp = wp_next_scheduled('MakisRssCronjob');
    // unschedule previous event if any
    wp_unschedule_event($timestamp, 'MakisRssCronjob');
}

register_deactivation_hook(__FILE__, 'makisrss_cronstarter_deactivate');

// hook that function onto our scheduled event:
add_action('MakisRssCronjob', 'makisrss_load_posts_function');
?>