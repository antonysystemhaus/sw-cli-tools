<?php

namespace Shopware\Plugin\Services;

use Shopware\Plugin\Struct\Plugin;
use ShopwareCli\Services\IoService;
use ShopwareCli\Services\ProcessExecutor;

/**
 * Checks out a given plugin, activates it, adds it to the phpstorm vcs.xml, installs git hook
 */
class Install
{
    /**
     * @var Checkout
     */
    protected $checkout;

    /**
     * @var IoService
     */
    private $ioService;

    /**
     * @var ProcessExecutor
     */
    private $processExecutor;

    /**
     * @param Checkout $checkout
     * @param IoService $ioService
     * @param ProcessExecutor $processExecutor
     */
    public function __construct(Checkout $checkout, IoService $ioService, ProcessExecutor $processExecutor)
    {
        $this->checkout = $checkout;
        $this->ioService = $ioService;
        $this->processExecutor = $processExecutor;
    }

    /**
     * @param Plugin $plugin
     * @param string $shopwarePath
     * @param bool $inputActivate
     * @param string $branch
     * @param bool $useHttp
     */
    public function install(Plugin $plugin, $shopwarePath, $inputActivate = false, $branch = 'master', $useHttp = false)
    {
        $installationPath = $shopwarePath . '/engine/Shopware/Plugins/Local/';
        $pluginPath = $installationPath . '/' . $plugin->module . '/' . $plugin->name;

        if (!$plugin->module) {
            $installationPath = $shopwarePath . '/custom/plugins/';
            $pluginPath = $installationPath . '/' . $plugin->name;
        }

        $this->checkout->checkout(
            $plugin,
            $installationPath,
            $branch,
            $useHttp
        );

        if ($inputActivate) {
            $this->ioService->writeln(exec($shopwarePath . '/bin/console sw:plugin:refresh'));
            $this->ioService->writeln(exec($shopwarePath . '/bin/console sw:plugin:install --activate ' . $plugin->name));
        }

        $this->addPluginVcsMapping($plugin, $shopwarePath);
        $this->installGitHook($pluginPath);
    }

    /**
     * @param Plugin $plugin
     * @param string $shopwarePath
     */
    private function addPluginVcsMapping(Plugin $plugin, $shopwarePath)
    {
        $vcsMappingFile = $shopwarePath . '/.idea/vcs.xml';
        $pluginDestPath = $plugin->module . '/' . $plugin->name;

        if (!file_exists($vcsMappingFile)) {
            return;
        }

        $mapping = file_get_contents($vcsMappingFile);
        $xml = new \SimpleXMLElement($mapping);
        foreach ($xml->component->mapping as $mapping) {
            // if already mapped, return
            if (strpos($this->normalize($mapping['directory']), $this->normalize($pluginDestPath)) !== false) {
                return;
            }
        }

        $mappingDirectory = '$PROJECT_DIR$/engine/Shopware/Plugins/Local/' . $pluginDestPath;

        if (!$plugin->module) {
            $mappingDirectory = '$PROJECT_DIR$/custom/plugins/' . $plugin->name;
        }

        // mapping needs to be created
        $newMapping = $xml->component->addChild('mapping');
        $newMapping->addAttribute('vcs', 'Git');
        $newMapping->addAttribute('directory', $mappingDirectory);

        $xml->asXML($vcsMappingFile);
    }

    /**
     * Normalize directory strings to make them comparable
     *
     * @param $string
     * @return string
     */
    private function normalize($string)
    {
        return strtolower(str_replace(['/', '\\'], '-', $string));
    }

    /**
     * @param string $pluginPath
     */
    private function installGitHook($pluginPath)
    {
        $installShFile = $pluginPath . '/.githooks/install_hooks.sh';

        if (!file_exists($installShFile)) {
            return;
        }

        $this->processExecutor->execute($installShFile);
    }
}
