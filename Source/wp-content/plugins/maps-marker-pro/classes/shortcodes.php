<?php // Before trying to crack the plugin, please consider buying a pro license at https://www.mapsmarker.com/order - we have put hundreds of hours into the development of our plugin and without honest customers we will not be able to continue the development and support. Thanks for your understanding and your honesty!
namespace MMP; class Shortcodes { private $O17; private $shortcodes; public function __construct() { $spbas=maps_marker_pro::get_instance("MMP\134\123PBAS"); $this->O17 =($spbas->Ou() || $spbas->Oy(FALSE,TRUE)); $this->shortcodes =array(); } public function init() { add_action("in\151\164",array($this,"load_sho\162\164\143odes")); add_action("\167\160_footer",array($this,"add_sho\162\164\143odes"),025); } public function load_shortcodes() { if (!$this->O17) { add_shortcode(maps_marker_pro::$settings["shortcod\145"],array($this,"shortcod\145\137\145rror")); return; } add_shortcode(maps_marker_pro::$settings["shortco\144\145"],array($this,"map_s\150\157\162tcode")); } public function shortcode_error($atts) { $O13=maps_marker_pro::get_instance("\115\115P\134L10\156"); return "\074\144iv clas\163\075\042mmp-\151\156\166alid-l\151\143\145nse\042\076".sprintf($O13->kses__("Map cre\141\164\145d with\040\074a href=\042\045\061\044s\042\040\164itle=\042\045\062\044s\042\076\115aps M\141\162\153er Pr\157\074/a> co\165\154d not \142\145\040disp\154\141\171ed be\143\141\165se o\146\040\141n in\166\141\154id li\143\145\156se. \120\154ease c\157\156\164act \164\150\145 sit\145\040\157wner\040\146or mor\145\040detail\163\056","mmp"),"h\164\164\160s://www\056\155\141psmark\145\162\056com/ex\160\151\162ed/",esc_attr__("#1 mappin\147\040\160lugin \146\157\162 WordPr\145\163\163","mmp"))."\074\057div>"; } public function map_shortcode($atts) { $db=maps_marker_pro::get_instance("MMP\134\104\102"); $mmp_settings=maps_marker_pro::get_instance("MMP\134\123\145\164tings"); $l18=maps_marker_pro::get_instance("\115MP\134API"); if ( isset ($atts["\154ayer"])) { $atts["\155\141\160"]=$atts["layer"]; } if ( isset ($atts["map"])) { $O18=$db->get_map($atts["\155ap"]); if (!$O18) { return $this->error(sprintf(esc_html__("\105rror: m\141\160\040could n\157\164\040be loa\144\145\144 - a m\141\160\040with I\104\040\0451\044s\040\144oes not\040\145xist. \120\154ease c\157\156\164act t\150\145\040site \157\167\156er.","mmp"),$atts["\155\141\160"])); } $type="\155ap"; $id=$atts["m\141\160"]; if (is_feed() || (function_exists("\151\163_amp_end\160\157\151nt") && is_amp_endpoint())) { ob_start(); ?><?php echo "\015\012\011\011\011\011\074p>\015\012\011\011\011\011\011"; ?><?= $O18->l19; ?><?php echo "\074br />\015\012\011\011\011\011\011\074a href\075\042"; ?><?= $l18->link( "\057fullscre\145\156\057{$id}/"); ?><?php echo "\042 titl\145\075\042"; ?><?= esc_html__("Em\142\145\144ded ma\160\040\055 show\040\151\156 fulls\143\162\145en mod\145","m\155\160"); ?><?php echo "\042\076\015\012\011\011\011\011\011\011\074img src\075\042"; ?><?= plugins_url("images/ma\160\055\162ss-fee\144\056\160ng",__DIR__); ?><?php echo "\042 \167\151\144th=\042\063\060\064\042 h\145\151\147ht=\042\061\071\067\042 \057\076\074br />\015\012\011\011\011\011\011\011"; ?><?= esc_html__("Embedded \155\141\160 - sho\167\040\151n fulls\143\162\145en mod\145","mmp"); ?><?php echo "\015\012\011\011\011\011\011\074/a>\015\012\011\011\011\011\074\057p>\015\012\011\011\011\011"; ?><?php return ob_get_clean(); } } else if ( isset ($atts["mark\145\162"])) { if (!$db->get_marker($atts["marke\162"])) { return $this->error(sprintf(esc_html__("Error: m\141\160\040could \156\157\164 be loa\144\145\144 - a m\141\162\153er wit\150\040\111D %1\044\163\040does n\157\164\040exis\164\056\040Plea\163\145\040cont\141\143\164 the\040\163\151te ow\156\145\162.","mmp"),$atts["\155\141\162ker"])); } $type="\155arker"; $id=$atts["marker"]; } else if ( isset ($atts["cus\164\157\155"])) { $O18=$db->get_map($atts["custom"]); if (!$O18) { return $this->error(esc_html__("\105\162ror: ma\160\040\143ould n\157\164\040be loa\144\145\144 - inv\141\154\151d short\143\157\144e. Pl\145\141\163e con\164\141\143t the\040\163\151te o\167\156\145r.","mmp")); } $type="custo\155"; $id=$atts["cust\157\155"]; } else { return $this->error(esc_html__("Error: \155\141\160 could \156\157\164 be loa\144\145\144 - inva\154\151\144 short\143\157\144e. Ple\141\163\145 conta\143\164\040the \163\151\164e own\145\162\056","mmp")); } $uid=( isset ($atts["uid"])) ? esc_js($atts["uid"]): substr(md5(rand()),0,8); $O19=( isset ($atts["mar\153\145\162s"])) ? "\133".$db->sanitize_ids($atts["markers"],TRUE)."]": "[]"; $callback=( isset ($atts["callb\141\143\153"])) ? esc_js($atts["cal\154\142\141ck"]): "n\165\154\154"; if ( isset ($atts["\150ighlight\155\141\162ker"])) { $atts["\150\151\147hlight"]=$atts["highl\151\147\150tmarker"]; } $l1a=( isset ($atts["hi\147\150\154ight"])) ? absint($atts["highli\147\150\164"]): "nul\154"; $fullscreen=( isset ($atts["\146ullscree\156"])) ? "\164rue": "\146\141lse"; $O1a=( isset ($atts["\143\150\141rt"])) ? $atts["\143\150\141rt"]: "\156\165ll"; $l1b=array(); foreach (array_keys($mmp_settings->map_settings_sanity()) as $setting) { $l1b[strtolower($setting)]=$setting; } $settings=array(); foreach ($atts as $lj => $O1b) { if ( isset ($l1b[$lj])) { $settings[$l1b[$lj]]=$O1b; } } $settings=$mmp_settings->validate_map_settings($settings,TRUE); wp_enqueue_style("mapsmar\153\145\162pro"); if (is_rtl()) { wp_enqueue_style("ma\160\163\155arkerpr\157\055\162tl"); } if (maps_marker_pro::$settings["googl\145\101\160iKey"]) { wp_enqueue_script("\155\155\160-goog\154\145\155aps"); } wp_enqueue_script("map\163\155\141rkerpro"); $this->shortcodes[]=array("uid" => $uid,"type" => $type,"\151d" => $id,"ma\162\153\145rs" => $O19,"\157verride\163" => json_encode($settings,JSON_FORCE_OBJECT),"\143allback" => $callback,"\150ighlight" => $l1a,"fu\154\154\163creen" => $fullscreen,"\143\150\141rt" => $O1a); return "<\144\151\166 id=\042\155\141\160s-marke\162\055\160ro-".$uid."\042 class=\042\155\141ps-marke\162\055\160ro\042\076\074\057div>"; } public function error($message) { return "<div cla\163\163\075\042mmp\055\155\141p-err\157\162\042>".$message."\074\057div>"; } public function add_shortcodes() {; ?><?php echo "\015\012\011\011\074scrip\164\076\015\012\011\011\011\144ocume\156\164\056addEv\145\156\164Listen\145\162\050'DOMC\157\156\164entL\157\141\144ed', \146\165\156ction\050\051 \173\015\012\011\011\011\011"; ?><?php foreach ($this->shortcodes as $shortcode):; ?><?php echo "\015\012\011\011\011\011\011ne\167\040\115apsMar\153\145\162Pro(\173\015\012\011\011\011\011\011\011uid:\040\047"; ?><?= $shortcode["\165\151\144"]; ?><?php echo "',\015\012\011\011\011\011\011\011\164ype: '"; ?><?= $shortcode["\164\171\160e"]; ?><?php echo "',\015\012\011\011\011\011\011\011\151d: '"; ?><?= $shortcode["id"]; ?><?php echo "',\015\012\011\011\011\011\011\011\155arkers:\040"; ?><?= $shortcode["\155\141\162kers"]; ?><?php echo "\054\015\012\011\011\011\011\011\011o\166\145\162rides:\040"; ?><?= $shortcode["overri\144\145\163"]; ?><?php echo ",\015\012\011\011\011\011\011\011\143allback\072\040"; ?><?= $shortcode["\143allback"]; ?><?php echo ",\015\012\011\011\011\011\011\011highlig\150\164\072 "; ?><?= $shortcode["high\154\151\147ht"]; ?><?php echo ",\015\012\011\011\011\011\011\011\146ullscre\145\156\072 "; ?><?= $shortcode["\146\165llscree\156"]; ?><?php echo "\054\015\012\011\011\011\011\011\011\143\150art: '"; ?><?= $shortcode["\143\150art"]; ?><?php echo "'\015\012\011\011\011\011\011\175\051;\015\012\011\011\011\011"; ?><?php endforeach; ?><?php echo "\015\012\011\011\011\175\051\073\015\012\011\011\074/scr\151\160\164>\015\012\011\011"; ?><?php } }