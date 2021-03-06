<?php

namespace MailPoet\Cron;

if (!defined('ABSPATH')) exit;


use MailPoet\Router\Endpoints\CronDaemon as CronDaemonEndpoint;
use MailPoet\Router\Router;
use MailPoet\Settings\SettingsController;
use MailPoet\Util\Security;
use MailPoet\WP\Functions as WPFunctions;

class CronHelper {
  const DAEMON_EXECUTION_LIMIT = 20; // seconds
  const DAEMON_REQUEST_TIMEOUT = 5; // seconds
  const DAEMON_SETTING = 'cron_daemon';
  const DAEMON_STATUS_ACTIVE = 'active';
  const DAEMON_STATUS_INACTIVE = 'inactive';

  // Error codes
  const DAEMON_EXECUTION_LIMIT_REACHED = 1001;

  /** @var SettingsController */
  private $settings;

  /** @var WPFunctions */
  private $wp;

  public function __construct(SettingsController $settings, WPFunctions $wp) {
    $this->settings = $settings;
    $this->wp = $wp;
  }

  function getDaemonExecutionLimit() {
    $limit = $this->wp->applyFilters('mailpoet_cron_get_execution_limit', self::DAEMON_EXECUTION_LIMIT);
    return $limit;
  }

  function getDaemonExecutionTimeout() {
    $limit = $this->getDaemonExecutionLimit();
    $timeout = $limit * 1.75;
    return $this->wp->applyFilters('mailpoet_cron_get_execution_timeout', $timeout);
  }

  function createDaemon($token) {
    $daemon = [
      'token' => $token,
      'status' => self::DAEMON_STATUS_ACTIVE,
      'run_accessed_at' => null,
      'run_started_at' => null,
      'run_completed_at' => null,
      'last_error' => null,
      'last_error_date' => null,
    ];
    $this->saveDaemon($daemon);
    return $daemon;
  }

  function restartDaemon($token) {
    return $this->createDaemon($token);
  }

  function getDaemon() {
    return $this->settings->fetch(self::DAEMON_SETTING);
  }

  function saveDaemonLastError($error) {
    $daemon = $this->getDaemon();
    if ($daemon) {
      $daemon['last_error'] = $error;
      $daemon['last_error_date'] = time();
      $this->saveDaemon($daemon);
    }
  }

  function saveDaemonRunCompleted($run_completed_at) {
    $daemon = $this->getDaemon();
    if ($daemon) {
      $daemon['run_completed_at'] = $run_completed_at;
      $this->saveDaemon($daemon);
    }
  }

  function saveDaemon($daemon) {
    $daemon['updated_at'] = time();
    $this->settings->set(
      self::DAEMON_SETTING,
      $daemon
    );
  }

  function deactivateDaemon($daemon) {
    $daemon['status'] = self::DAEMON_STATUS_INACTIVE;
    $this->settings->set(
      self::DAEMON_SETTING,
      $daemon
    );
  }

  function createToken() {
    return Security::generateRandomString();
  }

  function pingDaemon() {
    $url = $this->getCronUrl(
      CronDaemonEndpoint::ACTION_PING_RESPONSE
    );
    $result = $this->queryCronUrl($url);
    if (is_wp_error($result)) return $result->get_error_message();
    $response = $this->wp->wpRemoteRetrieveBody($result);
    $response = substr(trim($response), -strlen(DaemonHttpRunner::PING_SUCCESS_RESPONSE)) === DaemonHttpRunner::PING_SUCCESS_RESPONSE ?
      DaemonHttpRunner::PING_SUCCESS_RESPONSE :
      $response;
    return $response;
  }

  function validatePingResponse($response) {
    return $response === DaemonHttpRunner::PING_SUCCESS_RESPONSE;
  }

  function accessDaemon($token) {
    $data = ['token' => $token];
    $url = $this->getCronUrl(
      CronDaemonEndpoint::ACTION_RUN,
      $data
    );
    $daemon = $this->getDaemon();
    if (!$daemon) {
      throw new \LogicException('Daemon does not exist.');
    }
    $daemon['run_accessed_at'] = time();
    $this->saveDaemon($daemon);
    $result = $this->queryCronUrl($url);
    return $this->wp->wpRemoteRetrieveBody($result);
  }

  /**
   * @return boolean|null
   */
  function isDaemonAccessible() {
    $daemon = $this->getDaemon();
    if (!$daemon || !isset($daemon['run_accessed_at']) || $daemon['run_accessed_at'] === null) {
      return null;
    }
    if ($daemon['run_accessed_at'] <= (int)$daemon['run_started_at']) {
      return true;
    }
    if (
      $daemon['run_accessed_at'] + self::DAEMON_REQUEST_TIMEOUT < time() &&
      $daemon['run_accessed_at'] > (int)$daemon['run_started_at']
    ) {
        return false;
    }
    return null;
  }

  function queryCronUrl($url) {
    $args = $this->wp->applyFilters(
      'mailpoet_cron_request_args',
      [
        'blocking' => true,
        'sslverify' => false,
        'timeout' => self::DAEMON_REQUEST_TIMEOUT,
        'user-agent' => 'MailPoet Cron',
      ]
    );
    return $this->wp->wpRemotePost($url, $args);
  }

  function getCronUrl($action, $data = false) {
    $url = Router::buildRequest(
      CronDaemonEndpoint::ENDPOINT,
      $action,
      $data
    );
    $custom_cron_url = $this->wp->applyFilters('mailpoet_cron_request_url', $url);
    return ($custom_cron_url === $url) ?
      str_replace(home_url(), $this->getSiteUrl(), $url) :
      $custom_cron_url;
  }

  function getSiteUrl($site_url = false) {
    // additional check for some sites running inside a virtual machine or behind
    // proxy where there could be different ports (e.g., host:8080 => guest:80)
    $site_url = ($site_url) ? $site_url : WPFunctions::get()->homeUrl();
    $parsed_url = parse_url($site_url);
    $scheme = '';
    if ($parsed_url['scheme'] === 'https') {
      $scheme = 'ssl://';
    }
    // 1. if site URL does not contain a port, return the URL
    if (empty($parsed_url['port'])) return $site_url;
    // 2. if site URL contains valid port, try connecting to it
    $fp = @fsockopen($scheme . $parsed_url['host'], $parsed_url['port'], $errno, $errstr, 1);
    if ($fp) return $site_url;
    // 3. if connection fails, attempt to connect the standard port derived from URL
    // schema
    $port = (strtolower($parsed_url['scheme']) === 'http') ? 80 : 443;
    $fp = @fsockopen($scheme . $parsed_url['host'], $port, $errno, $errstr, 1);
    if ($fp) return sprintf('%s://%s', $parsed_url['scheme'], $parsed_url['host']);
    // 4. throw an error if all connection attempts failed
    throw new \Exception(__('Site URL is unreachable.', 'mailpoet'));
  }

  function enforceExecutionLimit($timer) {
    $elapsed_time = microtime(true) - $timer;
    if ($elapsed_time >= $this->getDaemonExecutionLimit()) {
      throw new \Exception(__('Maximum execution time has been reached.', 'mailpoet'), self::DAEMON_EXECUTION_LIMIT_REACHED);
    }
  }
}
