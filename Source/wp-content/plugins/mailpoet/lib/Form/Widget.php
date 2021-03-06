<?php

namespace MailPoet\Form;

if (!defined('ABSPATH')) exit;


use MailPoet\API\JSON\API;
use MailPoet\Config\Renderer as ConfigRenderer;
use MailPoet\Form\Renderer as FormRenderer;
use MailPoet\Models\Form;
use MailPoet\Settings\SettingsController;
use MailPoet\Util\Security;
use MailPoet\WP\Functions as WPFunctions;

class Widget extends \WP_Widget {
  private $renderer;
  private $wp;

  /** @var AssetsController */
  private $assets_controller;

  function __construct() {
    parent::__construct(
      'mailpoet_form',
      WPFunctions::get()->__('MailPoet 3 Form', 'mailpoet'),
      ['description' => WPFunctions::get()->__('Add a newsletter subscription form', 'mailpoet')]
    );
    $this->wp = new WPFunctions;
    $this->renderer = new \MailPoet\Config\Renderer(!WP_DEBUG, !WP_DEBUG);
    $this->assets_controller = new AssetsController($this->wp, $this->renderer, SettingsController::getInstance());
    if (!is_admin()) {
      $this->setupIframe();
    } else {
      WPFunctions::get()->addAction('widgets_admin_page', [
        $this->assets_controller,
        'setupAdminWidgetPageDependencies',
      ]);
    }
  }

  function setupIframe() {
    $form_id = (isset($_GET['mailpoet_form_iframe']) ? (int)$_GET['mailpoet_form_iframe'] : 0);
    if (!$form_id || !Form::findOne($form_id)) return;

    $form_html = $this->widget(
      [
        'form' => $form_id,
        'form_type' => 'iframe',
      ]
    );

    $scripts = $this->assets_controller->printScripts();

    // language attributes
    $language_attributes = [];
    $is_rtl = (bool)(function_exists('is_rtl') && WPFunctions::get()->isRtl());

    if ($is_rtl) {
      $language_attributes[] = 'dir="rtl"';
    }

    if (get_option('html_type') === 'text/html') {
      $language_attributes[] = sprintf('lang="%s"', WPFunctions::get()->getBloginfo('language'));
    }

    $language_attributes = WPFunctions::get()->applyFilters(
      'language_attributes', implode(' ', $language_attributes)
    );

    $data = [
      'language_attributes' => $language_attributes,
      'scripts' => $scripts,
      'form' => $form_html,
      'mailpoet_form' => [
        'ajax_url' => WPFunctions::get()->adminUrl('admin-ajax.php', 'absolute'),
        'is_rtl' => $is_rtl,
      ],
    ];

    try {
      echo $this->renderer->render('form/iframe.html', $data);
    } catch (\Exception $e) {
      echo $e->getMessage();
    }

    exit();
  }

  /**
   * Save the new widget's title.
   */
  public function update($new_instance, $old_instance) {
    $instance = $old_instance;
    $instance['title'] = strip_tags($new_instance['title']);
    $instance['form'] = (int)$new_instance['form'];
    return $instance;
  }

  /**
   * Output the widget's option form.
   */
  public function form($instance) {
    $instance = WPFunctions::get()->wpParseArgs(
      (array)$instance,
      [
        'title' => WPFunctions::get()->__('Subscribe to Our Newsletter', 'mailpoet'),
      ]
    );

    $form_edit_url = WPFunctions::get()->adminUrl('admin.php?page=mailpoet-form-editor&id=');

    // set title
    $title = isset($instance['title']) ? strip_tags($instance['title']) : '';

    // set form
    $selected_form = isset($instance['form']) ? (int)($instance['form']) : 0;

    // get forms list
    $forms = Form::getPublished()->orderByAsc('name')->findArray();
    ?><p>
      <label for="<?php $this->get_field_id( 'title' ) ?>"><?php WPFunctions::get()->_e('Title:', 'mailpoet'); ?></label>
      <input
        type="text"
        class="widefat"
        id="<?php echo $this->get_field_id('title') ?>"
        name="<?php echo $this->get_field_name('title'); ?>"
        value="<?php echo WPFunctions::get()->escAttr($title); ?>"
      />
    </p>
    <p>
      <select class="widefat" id="<?php echo $this->get_field_id('form') ?>" name="<?php echo $this->get_field_name('form'); ?>">
        <?php
        foreach ($forms as $form) {
          $is_selected = ($selected_form === (int)$form['id']) ? 'selected="selected"' : '';
          $form_name = $form['name'] ? $this->wp->escHtml($form['name']) : "({$this->wp->_x('no name', 'fallback for forms without a name in a form list')})"
          ?>
        <option value="<?php echo (int)$form['id']; ?>" <?php echo $is_selected; ?>><?php echo $form_name; ?></option>
        <?php }  ?>
      </select>
    </p>
    <p>
      <a href="javascript:;" onClick="createSubscriptionForm()" class="mailpoet_form_new"><?php WPFunctions::get()->_e('Create a new form', 'mailpoet'); ?></a>
    </p>
    <script type="text/javascript">
    function createSubscriptionForm() {
        MailPoet.Ajax.post({
          endpoint: 'forms',
          action: 'create',
          api_version: window.mailpoet_api_version
        }).done(function(response) {
          if (response.data && response.data.id) {
            window.location =
              "<?php echo $form_edit_url; ?>" + response.data.id;
          }
        }).fail((response) => {
          if (response.errors.length > 0) {
            MailPoet.Notice.error(
              response.errors.map((error) => { return error.message; }),
              { scroll: true }
            );
          }
        });
        return false;
    }
    </script>
    <?php
  }

  /**
   * Output the widget itself.
   */
  function widget($args, $instance = null) {
    $this->assets_controller->setupFrontEndDependencies();

    // turn $args into variables
    extract($args);

    if ($instance === null) {
      $instance = $args;
    }

    $title = $this->wp->applyFilters(
      'widget_title',
      !empty($instance['title']) ? $instance['title'] : '',
      $instance,
      $this->id_base
    );

    // get form
    $form = Form::getPublished()->findOne($instance['form']);
    if (!$form) return '';

    $form = $form->asArray();
    $form_type = 'widget';
    if (isset($instance['form_type']) && in_array(
        $instance['form_type'],
        [
          'html',
          'php',
          'iframe',
          'shortcode',
        ]
      )) {
      $form_type = $instance['form_type'];
    }

    $body = (isset($form['body']) ? $form['body'] : []);
    $output = '';

    if (!empty($body)) {
      $form_id = $this->id_base . '_' . $form['id'];
      $data = [
        'form_id' => $form_id,
        'form_type' => $form_type,
        'form' => $form,
        'title' => $title,
        'styles' => FormRenderer::renderStyles($form, '#' . $form_id),
        'html' => FormRenderer::renderHTML($form),
        'before_widget' => (!empty($before_widget) ? $before_widget : ''),
        'after_widget' => (!empty($after_widget) ? $after_widget : ''),
        'before_title' => (!empty($before_title) ? $before_title : ''),
        'after_title' => (!empty($after_title) ? $after_title : ''),
      ];

      // (POST) non ajax success/error variables
      $data['success'] = (
        (isset($_GET['mailpoet_success']))
        &&
        ((int)$_GET['mailpoet_success'] === (int)$form['id'])
      );
      $data['error'] = (
        (isset($_GET['mailpoet_error']))
        &&
        ((int)$_GET['mailpoet_error'] === (int)$form['id'])
      );

      // generate security token
      $data['token'] = Security::generateToken();

      // add API version
      $data['api_version'] = API::CURRENT_VERSION;

      // render form
      $renderer = new ConfigRenderer();
      try {
        $output = $renderer->render('form/widget.html', $data);
        $output = WPFunctions::get()->doShortcode($output);
        $output = $this->wp->applyFilters('mailpoet_form_widget_post_process', $output);
      } catch (\Exception $e) {
        $output = $e->getMessage();
      }
    }

    if ($form_type === 'widget') {
      echo $output;
    } else {
      return $output;
    }
  }
}
