<?php

namespace MailPoet\Newsletter\Renderer\Blocks;

if (!defined('ABSPATH')) exit;


use MailPoet\Newsletter\Renderer\EscapeHelper as EHelper;

class Spacer {
  static function render($element) {
    $height = (int)$element['styles']['block']['height'];
    $background_color = EHelper::escapeHtmlAttr($element['styles']['block']['backgroundColor']);
    $template = '
      <tr>
        <td class="mailpoet_spacer" ' .
      (($background_color !== 'transparent') ? 'bgcolor="' . $background_color . '" ' : '') .
      'height="' . $height . '" valign="top"></td>
      </tr>';
    return $template;
  }
}
