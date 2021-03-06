<?php

namespace MailPoetVendor\Carbon;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Composer\Composer;
use MailPoetVendor\Composer\Config;
use MailPoetVendor\Composer\IO\ConsoleIO;
use MailPoetVendor\Composer\Script\Event as ScriptEvent;
use MailPoetVendor\Symfony\Component\Console\Helper\HelperSet;
use MailPoetVendor\Symfony\Component\Console\Helper\QuestionHelper;
use MailPoetVendor\Symfony\Component\Console\Input\StringInput;
use MailPoetVendor\Symfony\Component\Console\Output\ConsoleOutput;
use MailPoetVendor\UpdateHelper\UpdateHelper;
use MailPoetVendor\UpdateHelper\UpdateHelperInterface;
class Upgrade implements \MailPoetVendor\UpdateHelper\UpdateHelperInterface
{
    const ASK_ON_UPDATE = \false;
    const SUGGEST_ON_UPDATE = \false;
    protected static $laravelLibraries = array('laravel/framework' => '5.8.0', 'laravel/cashier' => '9.0.1', 'illuminate/support' => '5.8.0', 'laravel/dusk' => '5.0.0');
    protected static $otherLibraries = array('spatie/laravel-analytics' => '3.6.4', 'jenssegers/date' => '3.5.0');
    /**
     * @param \UpdateHelper\UpdateHelper $helper
     */
    public function check(\MailPoetVendor\UpdateHelper\UpdateHelper $helper)
    {
        $helper->write(array('Carbon 1 is deprecated, see how to migrate to Carbon 2.', 'https://carbon.nesbot.com/docs/#api-carbon-2'));
        if (static::SUGGEST_ON_UPDATE || static::ASK_ON_UPDATE || $helper->getIo()->isVerbose()) {
            $laravelUpdate = array();
            foreach (static::$laravelLibraries as $name => $version) {
                if ($helper->hasAsDependency($name) && $helper->isDependencyLesserThan($name, $version)) {
                    $laravelUpdate[$name] = $version;
                }
            }
            if (\count($laravelUpdate)) {
                $output = array('    Please consider upgrading your Laravel dependencies to be compatible with Carbon 2:');
                foreach ($laravelUpdate as $name => $version) {
                    $output[] = "      - {$name} at least to version {$version}";
                }
                $output[] = '';
                $output[] = "    If you can't update Laravel, check https://carbon.nesbot.com/ to see how to";
                $output[] = '    install Carbon 2 using alias version and our adapter kylekatarnls/laravel-carbon-2';
                $output[] = '';
                $helper->write($output);
            }
            foreach (static::$otherLibraries as $name => $version) {
                if ($helper->hasAsDependency($name) && $helper->isDependencyLesserThan($name, $version)) {
                    $helper->write("    Please consider upgrading {$name} at least to {$version} to be compatible with Carbon 2.\n");
                }
            }
            if (static::ASK_ON_UPDATE) {
                static::askForUpgrade($helper);
                return;
            }
        }
        $path = \implode(\DIRECTORY_SEPARATOR, array('.', 'vendor', 'bin', 'upgrade-carbon'));
        if (!\file_exists($path)) {
            $path = \realpath(__DIR__ . '/../../bin/upgrade-carbon');
        }
        $helper->write('    You can run ' . \escapeshellarg($path) . ' to get help in updating carbon and other frameworks and libraries that depend on it.');
    }
    private static function getUpgradeQuestion($upgrades)
    {
        $message = "Do you want us to try the following upgrade:\n";
        foreach ($upgrades as $name => $version) {
            $message .= "  - {$name}: {$version}\n";
        }
        return $message . '[Y/N] ';
    }
    public static function askForUpgrade(\MailPoetVendor\UpdateHelper\UpdateHelper $helper, $upgradeIfNotInteractive = \false)
    {
        $upgrades = array('nesbot/carbon' => '^2.0.0');
        foreach (array(static::$laravelLibraries, static::$otherLibraries) as $libraries) {
            foreach ($libraries as $name => $version) {
                if ($helper->hasAsDependency($name) && $helper->isDependencyLesserThan($name, $version)) {
                    $upgrades[$name] = "^{$version}";
                }
            }
        }
        $shouldUpgrade = $helper->isInteractive() ? $helper->getIo()->askConfirmation(static::getUpgradeQuestion($upgrades)) : $upgradeIfNotInteractive;
        if ($shouldUpgrade) {
            $helper->setDependencyVersions($upgrades)->update();
        }
    }
    public static function upgrade(\MailPoetVendor\Composer\Script\Event $event = null)
    {
        if (!$event) {
            $composer = new \MailPoetVendor\Composer\Composer();
            $baseDir = __DIR__ . '/../..';
            if (\file_exists("{$baseDir}/autoload.php")) {
                $baseDir .= '/..';
            }
            $composer->setConfig(new \MailPoetVendor\Composer\Config(\true, $baseDir));
            $event = new \MailPoetVendor\Composer\Script\Event('upgrade-carbon', $composer, new \MailPoetVendor\Composer\IO\ConsoleIO(new \MailPoetVendor\Symfony\Component\Console\Input\StringInput(''), new \MailPoetVendor\Symfony\Component\Console\Output\ConsoleOutput(), new \MailPoetVendor\Symfony\Component\Console\Helper\HelperSet(array(new \MailPoetVendor\Symfony\Component\Console\Helper\QuestionHelper()))));
        }
        static::askForUpgrade(new \MailPoetVendor\UpdateHelper\UpdateHelper($event), \true);
    }
}
